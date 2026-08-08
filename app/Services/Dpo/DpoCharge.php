<?php

namespace App\Services\Dpo;

use App\Services\CurrencyConverter;
use App\Services\EffectiveRates;

/**
 * Works out what DPO should actually be asked to take, and pins the rate that
 * decision was made at.
 *
 * The ledger always records the plan's own currency. This is the other half of
 * the story: the figure that left the customer's card, frozen the way an
 * invoice freezes its rate, so a receipt stays true after the next FX sync.
 */
class DpoCharge
{
    public function __construct(private EffectiveRates $rates) {}

    /**
     * The currencies a subscriber may actually be offered for this plan —
     * the plan's own, plus any whitelisted one we currently hold a rate for.
     *
     * Gating here rather than at validation keeps a currency the FX table
     * cannot price from ever reaching the screen. `exchange-rates:sync` may
     * simply never have run.
     *
     * @return list<string>
     */
    public function availableCurrencies(string $planCurrency): array
    {
        $allowed = (array) config('services.dpo.allowed_currencies', []);
        $converter = new CurrencyConverter($this->rates->tableFor(null));

        return array_values(array_filter(
            array_unique([strtoupper($planCurrency), ...array_map('strtoupper', $allowed)]),
            fn (string $code) => $code === strtoupper($planCurrency)
                || $converter->canConvert($planCurrency, $code),
        ));
    }

    /**
     * Convert `$amount` out of the plan's currency, with the rate stamped.
     *
     * Returns null when the pair cannot be priced. Callers must refuse the
     * payment rather than assume parity — passing K100,000 off as $100,000 is
     * the one failure worse than not taking the money at all.
     *
     * The company is deliberately null. CompanyExchangeRate overrides exist so
     * a tenant can price their own invoices; letting one move what Nilo
     * charges for its own subscription would be a self-service discount.
     *
     * @return array{amount: float, currency: string, rate: float|null, as_of: \Illuminate\Support\Carbon|null}|null
     */
    public function resolve(float $amount, string $planCurrency, ?string $requested): ?array
    {
        $currency = strtoupper($requested ?: $planCurrency);
        $planCurrency = strtoupper($planCurrency);

        if ($currency === $planCurrency) {
            return [
                'amount' => round($amount, 2),
                'currency' => $currency,
                'rate' => null,
                'as_of' => null,
            ];
        }

        $stamp = $this->rates->stampFor(null, $planCurrency);
        $converted = (new CurrencyConverter($this->rates->tableFor(null)))
            ->convert($amount, $planCurrency, $currency);

        if ($stamp === null || $converted === null) {
            return null;
        }

        $markup = (float) config('services.dpo.fx_markup', 0);

        return [
            'amount' => round($converted * (1 + $markup / 100), 2),
            'currency' => $currency,
            'rate' => $stamp['rate'],
            'as_of' => $stamp['as_of'],
        ];
    }
}
