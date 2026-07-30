<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a fresh rate table from the upstream provider.
 *
 * Extracted from the console command because the settings page can trigger a
 * sync on demand as well, and both callers must behave identically — above all
 * on failure, where existing rates are deliberately left in place. Stale rates
 * are far more useful than an empty table, which would turn every converted
 * figure on the dashboard into a dash.
 */
class ExchangeRateSynchronizer
{
    public function __construct(protected ExchangeRateProvider $provider) {}

    public function sync(string $base = ExchangeRate::BASE): ExchangeRateSyncResult
    {
        $base = strtoupper($base);

        try {
            $snapshot = $this->provider->fetch($base);
        } catch (Throwable $e) {
            Log::error('Exchange rate sync failed', ['error' => $e->getMessage()]);

            return ExchangeRateSyncResult::failed($e->getMessage());
        }

        $now = now();
        $rows = [];

        foreach ($snapshot['rates'] as $code => $rate) {
            $rows[] = [
                'base_code' => $snapshot['base'],
                'quote_code' => $code,
                'rate' => $rate,
                'fetched_at' => $snapshot['fetched_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ExchangeRate::upsert($rows, ['base_code', 'quote_code'], ['rate', 'fetched_at', 'updated_at']);

        return ExchangeRateSyncResult::succeeded(
            count($rows),
            $snapshot['base'],
            $snapshot['fetched_at'],
        );
    }
}
