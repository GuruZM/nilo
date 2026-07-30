<?php

use App\Models\Company;
use App\Models\CompanyExchangeRate;
use App\Models\ExchangeRate;
use App\Services\EffectiveRates;
use Illuminate\Support\Carbon;

/**
 * Stores a synced rate, controlling when the sync claims to have written it.
 * `updated_at` is the precedence timestamp, so it has to be set explicitly
 * rather than left to the automatic value.
 */
function syncedRate(string $code, float $rate, ?Carbon $syncedAt = null): ExchangeRate
{
    $syncedAt ??= now();

    $stored = ExchangeRate::create([
        'base_code' => 'USD',
        'quote_code' => $code,
        'rate' => $rate,
        'fetched_at' => $syncedAt,
    ]);

    $stored->forceFill(['updated_at' => $syncedAt])->saveQuietly();

    return $stored->refresh();
}

function overrideRate(Company $company, string $code, float $rate, ?Carbon $setAt = null): CompanyExchangeRate
{
    return CompanyExchangeRate::create([
        'company_id' => $company->id,
        'base_code' => 'USD',
        'quote_code' => $code,
        'rate' => $rate,
        'set_at' => $setAt ?? now(),
    ]);
}

it('prefers an override set after the last sync', function () {
    $company = Company::factory()->create();

    syncedRate('ZMW', 26.85, Carbon::parse('2026-07-30 01:00:00'));
    overrideRate($company, 'ZMW', 27.50, Carbon::parse('2026-07-30 14:00:00'));

    expect(app(EffectiveRates::class)->rateFor($company->id, 'ZMW'))->toBe(27.50);
});

it('ignores an override set before the last sync', function () {
    $company = Company::factory()->create();

    overrideRate($company, 'ZMW', 27.50, Carbon::parse('2026-07-30 00:30:00'));
    syncedRate('ZMW', 26.85, Carbon::parse('2026-07-30 01:00:00'));

    expect(app(EffectiveRates::class)->rateFor($company->id, 'ZMW'))->toBe(26.85);
});

it('keeps an override for a currency the feed never returns', function () {
    $company = Company::factory()->create();

    syncedRate('EUR', 0.92, Carbon::parse('2026-07-30 01:00:00'));
    overrideRate($company, 'XOF', 600.0, Carbon::parse('2026-07-29 09:00:00'));

    expect(app(EffectiveRates::class)->rateFor($company->id, 'XOF'))->toBe(600.0);
});

it('does not leak one company override into another company', function () {
    $mine = Company::factory()->create();
    $theirs = Company::factory()->create();

    syncedRate('ZMW', 26.85, Carbon::parse('2026-07-30 01:00:00'));
    overrideRate($mine, 'ZMW', 27.50, Carbon::parse('2026-07-30 14:00:00'));

    expect(app(EffectiveRates::class)->rateFor($theirs->id, 'ZMW'))->toBe(26.85);
});

it('falls back to the synced table when no company is given', function () {
    $company = Company::factory()->create();

    syncedRate('ZMW', 26.85, Carbon::parse('2026-07-30 01:00:00'));
    overrideRate($company, 'ZMW', 27.50, Carbon::parse('2026-07-30 14:00:00'));

    expect(app(EffectiveRates::class)->rateFor(null, 'ZMW'))->toBe(26.85);
});

it('returns null for a currency with neither a synced rate nor an override', function () {
    $company = Company::factory()->create();

    syncedRate('ZMW', 26.85);

    expect(app(EffectiveRates::class)->rateFor($company->id, 'JPY'))->toBeNull();
});

it('merges overrides into the rate table', function () {
    $company = Company::factory()->create();

    syncedRate('ZMW', 26.85, Carbon::parse('2026-07-30 01:00:00'));
    syncedRate('EUR', 0.92, Carbon::parse('2026-07-30 01:00:00'));
    overrideRate($company, 'ZMW', 27.50, Carbon::parse('2026-07-30 14:00:00'));

    expect(app(EffectiveRates::class)->tableFor($company->id))
        ->toBe(['EUR' => 0.92, 'ZMW' => 27.50]);
});
