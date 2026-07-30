<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches daily FX rates from ExchangeRate-API.
 *
 * With a key configured it uses the keyed v6 endpoint, which carries no
 * attribution requirement. Without one it falls back to the keyless open
 * endpoint — convenient for local development, but that endpoint's terms
 * require a visible attribution link, so it is not the intended production mode.
 */
class ExchangeRateProvider
{
    /**
     * Fetch the current rate table.
     *
     * @return array{base: string, rates: array<string, float>, fetched_at: \Illuminate\Support\Carbon}
     *
     * @throws \RuntimeException when the feed is unreachable or malformed.
     */
    public function fetch(string $base = ExchangeRate::BASE): array
    {
        $response = Http::timeout((int) config('services.exchangerate.timeout'))
            ->retry(2, 500, throw: false)
            ->get($this->endpoint($base));

        if (! $response->successful()) {
            throw new RuntimeException(
                "Exchange rate request failed with status {$response->status()}."
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['result'] ?? null) !== 'success') {
            throw new RuntimeException(
                'Exchange rate response was not a success payload: '
                .($payload['error-type'] ?? 'unknown error').'.'
            );
        }

        /** The keyed endpoint names the map `conversion_rates`; the open one `rates`. */
        $rates = $payload['conversion_rates'] ?? $payload['rates'] ?? null;

        if (! is_array($rates) || $rates === []) {
            throw new RuntimeException('Exchange rate response contained no rates.');
        }

        return [
            'base' => strtoupper((string) ($payload['base_code'] ?? $base)),
            'rates' => $this->normalizeRates($rates),
            'fetched_at' => $this->fetchedAt($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $rates
     * @return array<string, float>
     */
    protected function normalizeRates(array $rates): array
    {
        $normalized = [];

        foreach ($rates as $code => $rate) {
            if (! is_numeric($rate) || (float) $rate <= 0) {
                continue;
            }

            $normalized[strtoupper((string) $code)] = (float) $rate;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function fetchedAt(array $payload): Carbon
    {
        $timestamp = $payload['time_last_update_unix'] ?? null;

        return is_numeric($timestamp)
            ? Carbon::createFromTimestampUTC((int) $timestamp)
            : Carbon::now();
    }

    protected function endpoint(string $base): string
    {
        $base = strtoupper($base);
        $key = config('services.exchangerate.key');

        if (filled($key)) {
            return rtrim((string) config('services.exchangerate.keyed_url'), '/')
                ."/{$key}/latest/{$base}";
        }

        return rtrim((string) config('services.exchangerate.open_url'), '/')
            ."/{$base}";
    }
}
