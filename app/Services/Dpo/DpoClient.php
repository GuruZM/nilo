<?php

namespace App\Services\Dpo;

use App\Services\Dpo\Data\DpoCreateTokenResult;
use App\Services\Dpo\Data\DpoVerifyTokenResult;
use App\Services\Dpo\Support\XmlCodec;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * A thin client over DPO's XML API. Amounts are handed over in major units —
 * the same decimal the payments ledger stores — so nothing in this app ever
 * has to round-trip money through cents.
 */
class DpoClient
{
    public function __construct(
        private readonly string $companyToken,
        private readonly string $serviceType,
        private readonly string $baseUrl,
        private readonly string $paymentUrl,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {}

    /**
     * @param  array{amount: string|float|int, currency: string, company_ref: string, redirect_url: string, back_url: string, ptl_hours?: int, customer_first_name?: string|null, customer_last_name?: string|null, customer_email?: string|null, customer_phone?: string|null, customer_country?: string|null, default_payment?: string|null, default_payment_country?: string|null}  $transaction
     * @param  array{description: string, date?: string}  $service
     */
    public function createToken(array $transaction, array $service): DpoCreateTokenResult
    {
        $payload = [
            'CompanyToken' => $this->companyToken,
            'Request' => 'createToken',
            'Transaction' => array_filter([
                'PaymentAmount' => $this->toWireAmount($transaction['amount']),
                'PaymentCurrency' => $transaction['currency'],
                'CompanyRef' => $transaction['company_ref'],
                // Every checkout attempt mints its own Payment row and its own
                // reference, so asking DPO to enforce uniqueness turns the
                // gateway itself into a third guard against a double charge.
                'CompanyRefUnique' => '1',
                'RedirectURL' => $transaction['redirect_url'],
                'BackURL' => $transaction['back_url'],
                'PTL' => (string) ($transaction['ptl_hours'] ?? 24),
                'customerFirstName' => $transaction['customer_first_name'] ?? null,
                'customerLastName' => $transaction['customer_last_name'] ?? null,
                'customerEmail' => $transaction['customer_email'] ?? null,
                'customerPhone' => $transaction['customer_phone'] ?? null,
                'customerCountry' => $transaction['customer_country'] ?? null,
                // DefaultPayment picks the tab the hosted page opens on, and
                // DefaultPaymentCountry pre-selects the country inside it, so
                // the customer is not asked for something we already know.
                'DefaultPayment' => $transaction['default_payment'] ?? null,
                'DefaultPaymentCountry' => $transaction['default_payment_country'] ?? null,
            ], fn (?string $value) => $value !== null),
            'Services' => [
                'Service' => [
                    'ServiceType' => $this->serviceType,
                    'ServiceDescription' => $service['description'],
                    'ServiceDate' => $service['date'] ?? now()->format('Y/m/d H:i'),
                ],
            ],
        ];

        // Deliberately not retried. A repeat of a money-moving call is the
        // wrong default, and CompanyRefUnique would reject the second attempt
        // anyway — a failed createToken needs a fresh Payment row, not a retry.
        $response = $this->post($payload);

        if ($this->resultCode($response) !== '000') {
            throw DpoException::fromResponse('createToken', $response);
        }

        return new DpoCreateTokenResult(
            transactionToken: (string) ($response['TransToken'] ?? ''),
            transactionRef: (string) ($response['TransRef'] ?? ''),
            raw: $response,
        );
    }

    /**
     * The authoritative status of a transaction.
     *
     * Unlike createToken this does not throw on a non-000 result: "declined"
     * and "cancelled" are answers, not failures, and callers branch on them.
     */
    public function verifyToken(string $transactionToken): DpoVerifyTokenResult
    {
        $response = $this->post([
            'CompanyToken' => $this->companyToken,
            'Request' => 'verifyToken',
            'TransactionToken' => $transactionToken,
        ], retries: 2);

        $resultCode = $this->resultCode($response);

        return new DpoVerifyTokenResult(
            resultCode: $resultCode,
            resultExplanation: (string) ($response['ResultExplanation'] ?? ''),
            status: $this->mapStatus($resultCode),
            raw: $response,
        );
    }

    public function cancelToken(string $transactionToken): void
    {
        $response = $this->post([
            'CompanyToken' => $this->companyToken,
            'Request' => 'cancelToken',
            'TransactionToken' => $transactionToken,
        ], retries: 2);

        if ($this->resultCode($response) !== '000') {
            throw DpoException::fromResponse('cancelToken', $response);
        }
    }

    public function paymentUrl(string $transactionToken): string
    {
        return sprintf('%s?ID=%s', $this->paymentUrl, urlencode($transactionToken));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(array $payload, int $retries = 0): array
    {
        $request = Http::withHeaders(['Content-Type' => 'application/xml'])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);

        if ($retries > 0) {
            $request->retry($retries, 500, throw: false);
        }

        try {
            $response = $request
                ->withBody(XmlCodec::encode($payload), 'application/xml')
                ->post($this->baseUrl);
        } catch (ConnectionException $exception) {
            // A timeout never sets a status code, so `failed()` below would
            // miss it entirely and the exception would escape as a 500 past
            // every caller that only guards against DpoException.
            throw new DpoException(
                "DPO request failed: {$exception->getMessage()}",
                ['error' => $exception->getMessage()],
            );
        }

        if ($response->failed()) {
            throw DpoException::fromResponse('request', [
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return XmlCodec::decode($response->body());
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resultCode(array $response): string
    {
        return (string) ($response['Result'] ?? '');
    }

    private function mapStatus(string $resultCode): string
    {
        return match ($resultCode) {
            '000', '001', '002' => 'paid',
            '900', '003', '005', '007' => 'pending',
            '903' => 'expired',
            '904' => 'cancelled',
            default => 'failed',
        };
    }

    private function toWireAmount(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
