<?php

use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Services\CurrencyRollup;

beforeEach(function () {
    foreach (['USD' => 1.0, 'ZMW' => 18.7, 'EUR' => 0.92] as $code => $rate) {
        ExchangeRate::create([
            'base_code' => 'USD',
            'quote_code' => $code,
            'rate' => $rate,
            'fetched_at' => now(),
        ]);
    }

    $this->company = Company::factory()->create(['currency_code' => 'ZMW']);
});

function invoicesFor(Company $company)
{
    return Invoice::query()->where('company_id', $company->id);
}

it('sums a single-currency query unchanged', function () {
    Invoice::factory()->in('ZMW')->worth(1000)->create(['company_id' => $this->company->id]);
    Invoice::factory()->in('ZMW')->worth(500)->create(['company_id' => $this->company->id]);

    $rollup = CurrencyRollup::into('ZMW');

    expect($rollup->sum(invoicesFor($this->company)))->toBe(1500.0)
        ->and($rollup->hasUnconvertible())->toBeFalse();
});

it('converts a mixed-currency query into the display currency', function () {
    Invoice::factory()->in('ZMW')->worth(1870)->create(['company_id' => $this->company->id]);
    Invoice::factory()->in('USD')->worth(100)->create(['company_id' => $this->company->id]);

    // 1870 ZMW is 100 USD, plus 100 USD = 200 USD.
    expect(CurrencyRollup::into('USD')->sum(invoicesFor($this->company)))->toBe(200.0);
});

it('no longer adds raw amounts across currencies', function () {
    Invoice::factory()->in('ZMW')->worth(1870)->create(['company_id' => $this->company->id]);
    Invoice::factory()->in('USD')->worth(100)->create(['company_id' => $this->company->id]);

    // The old behaviour would have produced 1970 by summing the columns blindly.
    expect(CurrencyRollup::into('USD')->sum(invoicesFor($this->company)))->not->toBe(1970.0);
});

it('reports amounts it could not convert instead of dropping them', function () {
    Invoice::factory()->in('ZMW')->worth(1870)->create(['company_id' => $this->company->id]);
    Invoice::factory()->in('XOF')->worth(50000)->create(['company_id' => $this->company->id]);

    $rollup = CurrencyRollup::into('USD');
    $total = $rollup->sum(invoicesFor($this->company));

    expect($total)->toBe(100.0)
        ->and($rollup->hasUnconvertible())->toBeTrue()
        ->and($rollup->unconvertible())->toBe(['XOF']);
});

it('respects filters already applied to the query', function () {
    Invoice::factory()->paid()->in('USD')->worth(100)->create(['company_id' => $this->company->id]);
    Invoice::factory()->pending()->in('USD')->worth(70)->create(['company_id' => $this->company->id]);

    $paid = invoicesFor($this->company)->where('status', 'paid');

    expect(CurrencyRollup::into('USD')->sum($paid))->toBe(100.0);
});

it('converts a single amount using a frozen rate', function () {
    $rollup = CurrencyRollup::into('USD');

    // Frozen at 15 per USD, even though today's stored rate is 18.7.
    expect($rollup->convert(1500.0, 'ZMW', 15.0))->toBe(100.0);
});

it('falls back to the latest rate when no frozen rate is given', function () {
    expect(CurrencyRollup::into('USD')->convert(1870.0, 'ZMW'))->toBe(100.0);
});

it('returns zero and records the shortfall for an unconvertible amount', function () {
    $rollup = CurrencyRollup::into('USD');

    expect($rollup->convert(50000.0, 'XOF'))->toBe(0.0)
        ->and($rollup->unconvertible())->toBe(['XOF']);
});

it('lists an unconvertible currency once, however often it is seen', function () {
    $rollup = CurrencyRollup::into('USD');

    $rollup->convert(1000.0, 'XOF');
    $rollup->convert(500.0, 'XOF');

    expect($rollup->unconvertible())->toBe(['XOF']);
});

it('lists every unconvertible currency, sorted', function () {
    $rollup = CurrencyRollup::into('USD');

    $rollup->convert(1000.0, 'XOF');
    $rollup->convert(500.0, 'GHS');

    expect($rollup->unconvertible())->toBe(['GHS', 'XOF']);
});

it('exposes when the rates were last refreshed', function () {
    $meta = CurrencyRollup::into('USD')->meta();

    expect($meta['display_code'])->toBe('USD')
        ->and($meta['rates_as_of'])->not->toBeNull();
});

it('reports no rate timestamp when nothing has been synced', function () {
    ExchangeRate::query()->delete();

    expect(CurrencyRollup::into('USD')->meta()['rates_as_of'])->toBeNull();
});

it('refuses to aggregate on an unsafe column name', function () {
    CurrencyRollup::into('USD')->sum(invoicesFor($this->company), 'total); drop table invoices --');
})->throws(InvalidArgumentException::class);

it('converts using a company override that is in force', function () {
    ExchangeRate::query()->where('quote_code', 'ZMW')
        ->update(['updated_at' => \Illuminate\Support\Carbon::parse('2026-07-30 01:00:00')]);

    \App\Models\CompanyExchangeRate::create([
        'company_id' => $this->company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 20.0,
        'set_at' => \Illuminate\Support\Carbon::parse('2026-07-30 14:00:00'),
    ]);

    Invoice::factory()->in('ZMW')->worth(2000)->create(['company_id' => $this->company->id]);

    /** 2000 ZMW at the override of 20/USD is 100 USD, not the synced 18.7. */
    $rollup = CurrencyRollup::into('USD', $this->company->id);

    expect($rollup->sum(invoicesFor($this->company)))->toBe(100.0);
});

it('ignores another company override when rolling up', function () {
    $other = Company::factory()->create();

    ExchangeRate::query()->where('quote_code', 'ZMW')
        ->update(['updated_at' => \Illuminate\Support\Carbon::parse('2026-07-30 01:00:00')]);

    \App\Models\CompanyExchangeRate::create([
        'company_id' => $other->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 20.0,
        'set_at' => \Illuminate\Support\Carbon::parse('2026-07-30 14:00:00'),
    ]);

    Invoice::factory()->in('ZMW')->worth(1870)->create(['company_id' => $this->company->id]);

    $rollup = CurrencyRollup::into('USD', $this->company->id);

    expect($rollup->sum(invoicesFor($this->company)))->toBe(100.0);
});

it('absorbs unconvertible codes from another rollup', function () {
    $a = CurrencyRollup::into('USD', $this->company->id);
    $b = CurrencyRollup::into('USD', $this->company->id);

    Invoice::factory()->in('XOF')->worth(500)->create(['company_id' => $this->company->id]);
    $b->sum(invoicesFor($this->company));

    expect($a->hasUnconvertible())->toBeFalse();

    $a->absorbUnconvertible($b);

    expect($a->unconvertible())->toBe(['XOF']);
});
