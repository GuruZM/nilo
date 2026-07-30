<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Totals money that was recorded in several currencies, expressed in one
 * display currency.
 *
 * Amounts that cannot be converted are reported rather than dropped. A revenue
 * figure that quietly omits money is worse than one that admits a gap.
 */
class CurrencyRollup
{
    /**
     * Currency codes encountered that had no usable rate.
     *
     * Only the codes are kept, not the amounts. A single rollup is reused for
     * several passes over the same invoices (totals, then the monthly series),
     * so a summed "amount left out" would count the same money repeatedly.
     *
     * @var array<string, true>
     */
    protected array $unconvertible = [];

    public function __construct(
        protected CurrencyConverter $converter,
        protected string $displayCode,
    ) {}

    /**
     * Build a rollup into the given display currency from the stored rates.
     *
     * Passing a company honours that company's in-force rate overrides;
     * omitting it uses the synced rates alone.
     */
    public static function into(string $displayCode, ?int $companyId = null): self
    {
        return new self(CurrencyConverter::forCompany($companyId), strtoupper($displayCode));
    }

    /**
     * Sum an invoice or quotation query, converting each currency's subtotal.
     *
     * The grouping happens in SQL, so this stays a single query regardless of
     * how many documents the company has.
     */
    public function sum(Builder $query, string $column = 'total'): float
    {
        if (! preg_match('/^[a-z_]+$/', $column)) {
            throw new InvalidArgumentException("Refusing to aggregate on [{$column}].");
        }

        $byCurrency = $query
            ->reorder()
            ->groupBy('currency_code')
            ->selectRaw("currency_code, SUM({$column}) as aggregate_amount")
            ->pluck('aggregate_amount', 'currency_code');

        $total = 0.0;

        foreach ($byCurrency as $code => $amount) {
            $converted = $this->converter->convert((float) $amount, (string) $code, $this->displayCode);

            if ($converted === null) {
                $this->recordUnconvertible((string) $code);

                continue;
            }

            $total += $converted;
        }

        return $total;
    }

    /**
     * Convert a single amount, preferring a rate frozen on the document.
     *
     * Returns 0.0 and records the shortfall when no rate is available, so that
     * callers building chart series get a numeric value either way.
     */
    public function convert(float $amount, string $from, ?float $frozenRate = null): float
    {
        $converted = $this->converter->convert($amount, $from, $this->displayCode, $frozenRate);

        if ($converted === null) {
            $this->recordUnconvertible($from);

            return 0.0;
        }

        return $converted;
    }

    /**
     * Currency codes this rollup could not convert, so the UI can say which
     * money is missing from a total rather than quietly omitting it.
     *
     * @return list<string>
     */
    public function unconvertible(): array
    {
        $codes = array_keys($this->unconvertible);

        sort($codes);

        return $codes;
    }

    public function hasUnconvertible(): bool
    {
        return $this->unconvertible !== [];
    }

    /**
     * Take on another rollup's unconvertible codes.
     *
     * A page that rolls several companies up separately — each with its own
     * rate overrides — still shows one "money left out" notice, so the gaps
     * have to be gathered back into a single rollup for `meta()`.
     */
    public function absorbUnconvertible(self $other): void
    {
        foreach ($other->unconvertible() as $code) {
            $this->recordUnconvertible($code);
        }
    }

    /**
     * A summary the frontend can show beside a converted figure.
     *
     * @return array{display_code: string, rates_as_of: string|null, unconvertible: list<string>}
     */
    public function meta(): array
    {
        return [
            'display_code' => $this->displayCode,
            'rates_as_of' => static::ratesAsOf()?->toIso8601String(),
            'unconvertible' => $this->unconvertible(),
        ];
    }

    /**
     * When the stored rates were last refreshed.
     */
    public static function ratesAsOf(): ?Carbon
    {
        return ExchangeRate::lastFetchedAt();
    }

    protected function recordUnconvertible(string $code): void
    {
        $this->unconvertible[strtoupper($code)] = true;
    }
}
