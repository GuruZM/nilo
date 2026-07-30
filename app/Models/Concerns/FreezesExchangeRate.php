<?php

namespace App\Models\Concerns;

use App\Services\EffectiveRates;
use Illuminate\Database\Eloquent\Model;

/**
 * Pins the FX rate of a document's currency at the moment it is created.
 *
 * An issued document must never change value because a rate moved afterwards,
 * so the rate travels with the row rather than being looked up at read time.
 */
trait FreezesExchangeRate
{
    protected static function bootFreezesExchangeRate(): void
    {
        static::creating(function (Model $model): void {
            $model->freezeExchangeRate();
        });
    }

    /**
     * Stamp the current rate onto the model, if one is available.
     *
     * Leaves the columns null when the rate table has nothing for this
     * currency. Issuing an invoice must never be blocked by the FX feed being
     * down; roll-ups fall back to the latest rate for these rows.
     */
    public function freezeExchangeRate(): void
    {
        if ($this->exchange_rate_to_base !== null || blank($this->currency_code)) {
            return;
        }

        $stamp = app(EffectiveRates::class)->stampFor(
            $this->company_id ? (int) $this->company_id : null,
            (string) $this->currency_code,
        );

        if ($stamp === null) {
            return;
        }

        $this->exchange_rate_to_base = $stamp['rate'];
        $this->exchange_rate_fetched_at = $stamp['as_of'];
    }
}
