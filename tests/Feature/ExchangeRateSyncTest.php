<?php

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * The shape the keyless open endpoint returns.
 *
 * @param  array<string, float>  $rates
 */
function rateResponse(array $rates = ['ZMW' => 18.7, 'EUR' => 0.92], int $updatedAt = 1785196800): array
{
    return [
        'result' => 'success',
        'base_code' => 'USD',
        'time_last_update_unix' => $updatedAt,
        'rates' => ['USD' => 1.0] + $rates,
    ];
}

it('stores the fetched rates', function () {
    Http::fake(['*' => Http::response(rateResponse())]);

    $this->artisan('exchange-rates:sync')->assertSuccessful();

    expect(ExchangeRate::count())->toBe(3);

    $kwacha = ExchangeRate::where('quote_code', 'ZMW')->first();

    expect($kwacha->base_code)->toBe('USD')
        ->and($kwacha->rate)->toBe(18.7);
});

it('records when the upstream data was last updated', function () {
    Http::fake(['*' => Http::response(rateResponse(updatedAt: 1785196800))]);

    $this->artisan('exchange-rates:sync')->assertSuccessful();

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->fetched_at->timestamp)
        ->toBe(1785196800);
});

it('updates rates in place rather than appending rows', function () {
    Http::fakeSequence()
        ->push(rateResponse(['ZMW' => 18.7]))
        ->push(rateResponse(['ZMW' => 19.4]));

    $this->artisan('exchange-rates:sync')->assertSuccessful();
    $this->artisan('exchange-rates:sync')->assertSuccessful();

    expect(ExchangeRate::where('quote_code', 'ZMW')->count())->toBe(1)
        ->and(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(19.4);
});

it('skips rates that are zero or non-numeric', function () {
    Http::fake(['*' => Http::response(rateResponse([
        'ZMW' => 18.7,
        'ZWG' => 0,
        'XXX' => 'not a number',
    ]))]);

    $this->artisan('exchange-rates:sync')->assertSuccessful();

    expect(ExchangeRate::whereIn('quote_code', ['ZWG', 'XXX'])->count())->toBe(0)
        ->and(ExchangeRate::where('quote_code', 'ZMW')->exists())->toBeTrue();
});

it('leaves existing rates untouched when the request fails', function () {
    Sleep::fake();

    Http::fakeSequence()
        ->push(rateResponse(['ZMW' => 18.7]))
        ->pushStatus(500)
        ->pushStatus(500)
        ->pushStatus(500);

    $this->artisan('exchange-rates:sync')->assertSuccessful();
    $this->artisan('exchange-rates:sync')->assertFailed();

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(18.7);
});

it('fails without writing when the payload reports an error', function () {
    Http::fake(['*' => Http::response([
        'result' => 'error',
        'error-type' => 'invalid-key',
    ])]);

    $this->artisan('exchange-rates:sync')->assertFailed();

    expect(ExchangeRate::count())->toBe(0);
});

it('fails when the payload carries no rates', function () {
    Http::fake(['*' => Http::response([
        'result' => 'success',
        'base_code' => 'USD',
        'rates' => [],
    ])]);

    $this->artisan('exchange-rates:sync')->assertFailed();

    expect(ExchangeRate::count())->toBe(0);
});

it('reads the keyed payload shape too', function () {
    Http::fake(['*' => Http::response([
        'result' => 'success',
        'base_code' => 'USD',
        'time_last_update_unix' => 1785196800,
        'conversion_rates' => ['USD' => 1.0, 'ZMW' => 18.7],
    ])]);

    $this->artisan('exchange-rates:sync')->assertSuccessful();

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(18.7);
});

it('calls the keyed endpoint when a key is configured', function () {
    config(['services.exchangerate.key' => 'secret-key']);

    Http::fake(['*' => Http::response(rateResponse())]);

    $this->artisan('exchange-rates:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/secret-key/latest/USD'));
});

it('falls back to the keyless endpoint when no key is configured', function () {
    config(['services.exchangerate.key' => null]);

    Http::fake(['*' => Http::response(rateResponse())]);

    $this->artisan('exchange-rates:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'open.er-api.com'));
});
