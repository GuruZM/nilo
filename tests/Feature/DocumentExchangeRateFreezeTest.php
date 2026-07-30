<?php

use App\Models\Company;
use App\Models\CompanyExchangeRate;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

function storeRate(string $code, float $rate, ?Carbon $fetchedAt = null): ExchangeRate
{
    return ExchangeRate::create([
        'base_code' => 'USD',
        'quote_code' => $code,
        'rate' => $rate,
        'fetched_at' => $fetchedAt ?? now(),
    ]);
}

it('stamps the current rate onto a new invoice', function () {
    $fetchedAt = Carbon::parse('2026-07-28 00:02:31');
    storeRate('ZMW', 18.7, $fetchedAt);

    $invoice = Invoice::factory()->in('ZMW')->create();

    expect($invoice->exchange_rate_to_base)->toBe(18.7)
        ->and($invoice->exchange_rate_fetched_at->timestamp)->toBe($fetchedAt->timestamp);
});

it('stamps the rate matching the document currency, not some other one', function () {
    storeRate('ZMW', 18.7);
    storeRate('EUR', 0.92);

    $invoice = Invoice::factory()->in('EUR')->create();

    expect($invoice->exchange_rate_to_base)->toBe(0.92);
});

it('leaves the rate null when none is stored for that currency', function () {
    storeRate('ZMW', 18.7);

    $invoice = Invoice::factory()->in('XOF')->create();

    expect($invoice->exchange_rate_to_base)->toBeNull()
        ->and($invoice->exchange_rate_fetched_at)->toBeNull();
});

it('still creates the invoice when the rate table is empty', function () {
    $invoice = Invoice::factory()->in('ZMW')->create();

    expect($invoice->exists)->toBeTrue()
        ->and($invoice->exchange_rate_to_base)->toBeNull();
});

it('does not re-stamp when the rate moves after issue', function () {
    storeRate('ZMW', 18.7);

    $invoice = Invoice::factory()->in('ZMW')->create();

    ExchangeRate::where('quote_code', 'ZMW')->update(['rate' => 25.0]);

    $invoice->update(['title' => 'Edited after the kwacha moved']);

    expect($invoice->fresh()->exchange_rate_to_base)->toBe(18.7);
});

it('respects a rate set explicitly rather than overwriting it', function () {
    storeRate('ZMW', 18.7);

    $invoice = Invoice::factory()->in('ZMW')->create([
        'exchange_rate_to_base' => 15.0,
    ]);

    expect($invoice->exchange_rate_to_base)->toBe(15.0);
});

it('stamps quotations the same way', function () {
    storeRate('ZMW', 18.7);

    $company = Company::factory()->create(['currency_code' => 'ZMW']);

    $quotation = $company->quotations()->create([
        'client_id' => \App\Models\Client::factory()->create(['company_id' => $company->id])->id,
        'number' => 'QUO-000001',
        'issue_date' => now()->toDateString(),
        'currency_code' => 'ZMW',
        'subtotal' => 1000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 1000,
        'status' => 'draft',
    ]);

    expect($quotation->exchange_rate_to_base)->toBe(18.7);
});

it('freezes a company override that is in force rather than the synced rate', function () {
    $company = Company::factory()->create();

    $synced = storeRate('ZMW', 26.85);
    $synced->forceFill(['updated_at' => Carbon::parse('2026-07-30 01:00:00')])->saveQuietly();

    CompanyExchangeRate::create([
        'company_id' => $company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.50,
        'set_at' => Carbon::parse('2026-07-30 14:00:00'),
    ]);

    $invoice = Invoice::factory()->for($company)->in('ZMW')->create();

    expect($invoice->exchange_rate_to_base)->toBe(27.50);
});

it('freezes the synced rate for a company with no override of its own', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();

    $synced = storeRate('ZMW', 26.85);
    $synced->forceFill(['updated_at' => Carbon::parse('2026-07-30 01:00:00')])->saveQuietly();

    CompanyExchangeRate::create([
        'company_id' => $other->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.50,
        'set_at' => Carbon::parse('2026-07-30 14:00:00'),
    ]);

    $invoice = Invoice::factory()->for($company)->in('ZMW')->create();

    expect($invoice->exchange_rate_to_base)->toBe(26.85);
});

it('freezes the synced rate once a sync has superseded the override', function () {
    $company = Company::factory()->create();

    CompanyExchangeRate::create([
        'company_id' => $company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.50,
        'set_at' => Carbon::parse('2026-07-30 00:30:00'),
    ]);

    $synced = storeRate('ZMW', 26.85);
    $synced->forceFill(['updated_at' => Carbon::parse('2026-07-30 01:00:00')])->saveQuietly();

    $invoice = Invoice::factory()->for($company)->in('ZMW')->create();

    expect($invoice->exchange_rate_to_base)->toBe(26.85);
});

it('stamps when the override was set as the rate timestamp', function () {
    $company = Company::factory()->create();
    $setAt = Carbon::parse('2026-07-30 14:00:00');

    $synced = storeRate('ZMW', 26.85, Carbon::parse('2026-07-30 00:00:00'));
    $synced->forceFill(['updated_at' => Carbon::parse('2026-07-30 01:00:00')])->saveQuietly();

    CompanyExchangeRate::create([
        'company_id' => $company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.50,
        'set_at' => $setAt,
    ]);

    $invoice = Invoice::factory()->for($company)->in('ZMW')->create();

    expect($invoice->exchange_rate_fetched_at->timestamp)->toBe($setAt->timestamp);
});
