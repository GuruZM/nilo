<?php

namespace App\Services;

use App\Models\CompanyExchangeRate;
use App\Models\ExchangeRate;

/**
 * Resolves the rate a company should actually be billed at, merging the
 * synced table with that company's hand-entered overrides.
 *
 * This is the only place that knows the precedence rule, and the rule is the
 * inverse of the usual one: an override wins only while it is newer than the
 * last sync that wrote the matching row. A rate somebody typed once and forgot
 * must not go on distorting figures, so the next sync quietly retires it.
 *
 * Two consequences of comparing against the sync fall out of that, both wanted:
 * a failed sync leaves an override in force (better than falling back to
 * something even older), and an override on a currency the feed does not carry
 * survives indefinitely (there is nothing to fall back to).
 */
class EffectiveRates
{
    /**
     * Quote-per-base rates for a company, keyed by currency code.
     *
     * Passing null yields the plain synced table, which is what callers with
     * no company in hand should get.
     *
     * @return array<string, float>
     */
    public function tableFor(?int $companyId, string $base = ExchangeRate::BASE): array
    {
        $rates = ExchangeRate::latestTable($base);

        if ($companyId === null) {
            return $rates;
        }

        return array_merge($rates, $this->overridesFor($companyId, $base));
    }

    /**
     * The effective rate for one currency, or null when it is unknown.
     */
    public function rateFor(?int $companyId, string $code, string $base = ExchangeRate::BASE): ?float
    {
        $code = strtoupper($code);

        if ($code === $base) {
            return 1.0;
        }

        $rate = $this->tableFor($companyId, $base)[$code] ?? null;

        return $rate !== null && $rate > 0 ? (float) $rate : null;
    }

    /**
     * The effective rate for one currency together with the moment it became
     * true — the override's `set_at`, or the sync's `fetched_at`.
     *
     * Documents freeze both, so the pair has to travel together.
     *
     * @return array{rate: float, as_of: \Illuminate\Support\Carbon}|null
     */
    public function stampFor(?int $companyId, string $code, string $base = ExchangeRate::BASE): ?array
    {
        $code = strtoupper($code);

        if ($companyId !== null) {
            $override = $this->overrideRowFor($companyId, $code, $base);

            if ($override !== null) {
                return ['rate' => (float) $override->rate, 'as_of' => $override->set_at];
            }
        }

        $synced = ExchangeRate::query()
            ->where('base_code', $base)
            ->where('quote_code', $code)
            ->first();

        if ($synced === null || $synced->rate <= 0) {
            return null;
        }

        return ['rate' => (float) $synced->rate, 'as_of' => $synced->fetched_at];
    }

    /**
     * A company's in-force override for one currency, if it has one.
     */
    protected function overrideRowFor(int $companyId, string $code, string $base): ?CompanyExchangeRate
    {
        $override = CompanyExchangeRate::query()
            ->where('company_id', $companyId)
            ->where('base_code', $base)
            ->where('quote_code', $code)
            ->first();

        if ($override === null || $override->rate <= 0) {
            return null;
        }

        $lastSync = ExchangeRate::query()
            ->where('base_code', $base)
            ->where('quote_code', $code)
            ->value('updated_at');

        if ($lastSync === null) {
            return $override;
        }

        return $override->set_at->greaterThan(\Illuminate\Support\Carbon::parse($lastSync))
            ? $override
            : null;
    }

    /**
     * A company's overrides that are still in force, keyed by currency code.
     *
     * @return array<string, float>
     */
    protected function overridesFor(int $companyId, string $base): array
    {
        $syncedAt = ExchangeRate::syncedAtByCode($base);

        return CompanyExchangeRate::query()
            ->where('company_id', $companyId)
            ->where('base_code', $base)
            ->get(['quote_code', 'rate', 'set_at'])
            ->filter(function (CompanyExchangeRate $override) use ($syncedAt): bool {
                $lastSync = $syncedAt[strtoupper($override->quote_code)] ?? null;

                /** Never synced, so nothing has had the chance to supersede it. */
                if ($lastSync === null) {
                    return true;
                }

                return $override->set_at->greaterThan($lastSync);
            })
            ->mapWithKeys(fn (CompanyExchangeRate $override) => [
                strtoupper($override->quote_code) => (float) $override->rate,
            ])
            ->all();
    }
}
