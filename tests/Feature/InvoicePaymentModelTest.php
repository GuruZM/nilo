<?php

use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoicePayment;

it('belongs to an invoice and a company', function () {
    $payment = InvoicePayment::factory()->create();

    expect($payment->invoice)->toBeInstanceOf(Invoice::class)
        ->and($payment->company)->toBeInstanceOf(Company::class);
});

it('casts money and dates', function () {
    $payment = InvoicePayment::factory()->create([
        'amount' => 1234.5,
        'paid_on' => '2026-07-15',
    ]);

    expect((float) $payment->fresh()->amount)->toBe(1234.50)
        ->and($payment->fresh()->paid_on->toDateString())->toBe('2026-07-15');
});

it('freezes the exchange rate when it is created', function () {
    /**
     * The trait only stamps a rate when one is available. A clean test
     * database has no synced rates at all, so a row is seeded here to
     * verify the freeze actually fires rather than testing the no-rate path.
     */
    ExchangeRate::create([
        'base_code' => ExchangeRate::BASE,
        'quote_code' => 'ZMW',
        'rate' => 18.5,
        'fetched_at' => now(),
    ]);

    $payment = InvoicePayment::factory()->create(['currency_code' => 'ZMW']);

    expect($payment->getAttributes())->toHaveKey('exchange_rate_to_base')
        ->and((float) $payment->exchange_rate_to_base)->toBe(18.5);
});

it('lists the payment methods it accepts', function () {
    expect(InvoicePayment::METHODS)->toBe([
        'cash', 'bank_transfer', 'mobile_money', 'cheque', 'card', 'other',
    ]);
});
