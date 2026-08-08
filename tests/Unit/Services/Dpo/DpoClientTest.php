<?php

use App\Services\Dpo\DpoClient;
use App\Services\Dpo\DpoException;
use App\Services\Dpo\Support\XmlCodec;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

// The Http facade needs a booted app; RefreshDatabase deliberately does not
// come with it, since nothing here touches the database.
uses(Tests\TestCase::class);

function dpoClient(): DpoClient
{
    return new DpoClient(
        companyToken: 'TEST-COMPANY-TOKEN',
        serviceType: '99',
        baseUrl: 'https://secure.3gdirectpay.com/API/v6/',
        paymentUrl: 'https://secure.3gdirectpay.com/payv2.php',
    );
}

function dpoXml(string $body): string
{
    return '<?xml version="1.0" encoding="utf-8"?><API3G>'.$body.'</API3G>';
}

function fakeDpo(string $body, int $status = 200): void
{
    Http::fake(['secure.3gdirectpay.com/*' => Http::response(dpoXml($body), $status)]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dpoTransaction(array $overrides = []): array
{
    return [
        'amount' => 100000,
        'currency' => 'ZMW',
        'company_ref' => 'REF-1',
        'redirect_url' => 'https://nilo.test/subscription/payment/dpo/return',
        'back_url' => 'https://nilo.test/subscription/payment/dpo/cancel',
        ...$overrides,
    ];
}

beforeEach(function () {
    Sleep::fake();
});

it('sends a well-formed createToken envelope', function () {
    fakeDpo('<Result>000</Result><TransToken>TOKEN123</TransToken><TransRef>REF123</TransRef>');

    dpoClient()->createToken(dpoTransaction(), ['description' => 'Standard plan']);

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, '<API3G>')
            && str_contains($body, '<CompanyToken>TEST-COMPANY-TOKEN</CompanyToken>')
            && str_contains($body, '<Request>createToken</Request>')
            && str_contains($body, '<ServiceType>99</ServiceType>')
            && str_contains($body, '<ServiceDescription>Standard plan</ServiceDescription>');
    });
});

/**
 * The one line in the port that had to change: zufc's client took cents, this
 * one takes the decimal the ledger stores — including the string Eloquent's
 * `decimal:2` cast hands back.
 */
it('writes the amount in major units whatever shape it arrives in', function (string|float|int $amount) {
    fakeDpo('<Result>000</Result><TransToken>TOKEN123</TransToken><TransRef>REF123</TransRef>');

    dpoClient()->createToken(dpoTransaction(['amount' => $amount]), ['description' => 'Standard plan']);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<PaymentAmount>100000.00</PaymentAmount>'));
})->with([
    'int' => 100000,
    'float' => 100000.0,
    'decimal cast string' => '100000.00',
]);

it('asks DPO to enforce reference uniqueness', function () {
    fakeDpo('<Result>000</Result><TransToken>TOKEN123</TransToken><TransRef>REF123</TransRef>');

    dpoClient()->createToken(dpoTransaction(), ['description' => 'Standard plan']);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<CompanyRefUnique>1</CompanyRefUnique>'));
});

it('parses the token and reference out of a successful createToken', function () {
    fakeDpo('<Result>000</Result><TransToken>TOKEN123</TransToken><TransRef>REF123</TransRef>');

    $result = dpoClient()->createToken(dpoTransaction(), ['description' => 'Standard plan']);

    expect($result->transactionToken)->toBe('TOKEN123')
        ->and($result->transactionRef)->toBe('REF123');
});

it('throws when createToken comes back with a failure result', function () {
    fakeDpo('<Result>801</Result><ResultExplanation>Missing Fields</ResultExplanation>');

    expect(fn () => dpoClient()->createToken(dpoTransaction(), ['description' => 'Standard plan']))
        ->toThrow(DpoException::class, 'Missing Fields');
});

it('throws when the gateway returns a server error', function () {
    fakeDpo('<Result>000</Result>', 500);

    expect(fn () => dpoClient()->createToken(dpoTransaction(), ['description' => 'Standard plan']))
        ->toThrow(DpoException::class);
});

/**
 * A timeout never sets a status code, so without an explicit catch this escapes
 * as a 500 past every caller that only guards against DpoException.
 */
it('turns a connection failure into a DpoException', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => dpoClient()->createToken(dpoTransaction(), ['description' => 'Standard plan']))
        ->toThrow(DpoException::class, 'timed out');
});

it('maps DPO result codes onto payment statuses', function (string $code, string $status) {
    fakeDpo("<Result>{$code}</Result><ResultExplanation>Whatever</ResultExplanation>");

    expect(dpoClient()->verifyToken('TOKEN123')->status)->toBe($status);
})->with([
    ['000', 'paid'],
    ['001', 'paid'],
    ['002', 'paid'],
    ['003', 'pending'],
    ['005', 'pending'],
    ['007', 'pending'],
    ['900', 'pending'],
    ['903', 'expired'],
    ['904', 'cancelled'],
    ['901', 'failed'],
]);

/**
 * Deliberately asymmetric with createToken: a decline is an answer, not a
 * transport failure, and every caller branches on it.
 */
it('does not throw when verifyToken reports a declined transaction', function () {
    fakeDpo('<Result>901</Result><ResultExplanation>Declined</ResultExplanation>');

    $result = dpoClient()->verifyToken('TOKEN123');

    expect($result->resultCode)->toBe('901')
        ->and($result->resultExplanation)->toBe('Declined')
        ->and($result->isTerminal())->toBeTrue();
});

it('treats a pending transaction as not yet terminal', function () {
    fakeDpo('<Result>900</Result><ResultExplanation>Not paid</ResultExplanation>');

    expect(dpoClient()->verifyToken('TOKEN123')->isTerminal())->toBeFalse();
});

it('throws when cancelToken is refused', function () {
    fakeDpo('<Result>904</Result><ResultExplanation>Already cancelled</ResultExplanation>');

    expect(fn () => dpoClient()->cancelToken('TOKEN123'))->toThrow(DpoException::class);
});

it('builds the hosted payment url from the token', function () {
    expect(dpoClient()->paymentUrl('TOKEN 123'))
        ->toBe('https://secure.3gdirectpay.com/payv2.php?ID=TOKEN+123');
});

it('escapes xml-significant characters in the payload', function () {
    $xml = XmlCodec::encode(['ServiceDescription' => 'Tools & Dies <Ltd>']);

    expect($xml)->toContain('Tools &amp; Dies &lt;Ltd&gt;');
});

it('rejects a response body that is not xml', function () {
    // What a WAF block page actually looks like — unclosed tags and all.
    expect(fn () => XmlCodec::decode('<html><body>403 Forbidden'))
        ->toThrow(RuntimeException::class, 'Invalid XML');
});
