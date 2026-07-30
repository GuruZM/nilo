<?php

namespace App\Services;

use App\Models\ExchangeRate;

/**
 * Converts amounts between currencies using a table of quote-per-base rates.
 *
 * Every rate is "units of currency X per 1 USD", so converting X to Y is
 * `amount / rate(X) * rate(Y)`. The base itself is implicitly rate 1.0.
 */
class CurrencyConverter
{
    /**
     * @param  array<string, float>  $rates  Quote-per-base rates keyed by currency code.
     */
    public function __construct(
        protected array $rates = [],
        protected string $base = ExchangeRate::BASE,
    ) {}

    /**
     * Build a converter from the rates currently stored in the database.
     */
    public static function fromDatabase(): self
    {
        return new self(ExchangeRate::latestTable());
    }

    /**
     * Build a converter that honours a company's in-force rate overrides.
     *
     * A null company falls back to the plain synced table.
     */
    public static function forCompany(?int $companyId): self
    {
        return new self(app(EffectiveRates::class)->tableFor($companyId));
    }

    /**
     * Convert an amount between two currencies.
     *
     * Returns null when either side has no rate. A missing rate is never
     * treated as 1.0 — silently passing K18,700 off as $18,700 would be far
     * worse than showing nothing.
     *
     * @param  float|null  $frozenFromRate  The rate stored on a document at issue time, if any.
     */
    public function convert(
        float $amount,
        string $from,
        string $to,
        ?float $frozenFromRate = null,
    ): ?float {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $fromRate = $frozenFromRate !== null && $frozenFromRate > 0
            ? $frozenFromRate
            : $this->rateFor($from);

        $toRate = $this->rateFor($to);

        if ($fromRate === null || $toRate === null) {
            return null;
        }

        return $amount / $fromRate * $toRate;
    }

    /**
     * Whether both currencies can be converted between.
     */
    public function canConvert(string $from, string $to): bool
    {
        return $this->convert(1.0, $from, $to) !== null;
    }

    /**
     * The stored rate for a currency, or null when it is unknown.
     */
    public function rateFor(string $code): ?float
    {
        $code = strtoupper($code);

        if ($code === $this->base) {
            return 1.0;
        }

        $rate = $this->rates[$code] ?? null;

        if ($rate === null || $rate <= 0) {
            return null;
        }

        return (float) $rate;
    }

    /**
     * Whether any rates are loaded at all.
     */
    public function isEmpty(): bool
    {
        return $this->rates === [];
    }
}
