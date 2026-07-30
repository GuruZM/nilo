<?php

use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\InvoicePayment;
use App\Services\InvoiceSettlement;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('reduces the balance by an issued credit note', function () {
    [, $invoice] = payableInvoiceContext();
    CreditNote::factory()->forInvoice($invoice, 2000)->issued()->create();

    $settlement = app(InvoiceSettlement::class);

    expect($settlement->amountCredited($invoice))->toBe(2000.0)
        ->and($settlement->balanceDue($invoice))->toBe(3000.0);
});

it('ignores a draft credit note', function () {
    [, $invoice] = payableInvoiceContext();
    CreditNote::factory()->forInvoice($invoice, 2000)->create();

    expect(app(InvoiceSettlement::class)->amountCredited($invoice))->toBe(0.0)
        ->and(app(InvoiceSettlement::class)->balanceDue($invoice))->toBe(5000.0);
});

it('settles an invoice with cash and credit together', function () {
    [, $invoice] = payableInvoiceContext();

    InvoicePayment::factory()->forInvoice($invoice, 3000)->create();
    CreditNote::factory()->forInvoice($invoice, 2000)->issued()->create();

    $settlement = app(InvoiceSettlement::class);
    $settlement->sync($invoice);

    expect($settlement->balanceDue($invoice->fresh()))->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('marks an invoice partially paid when only a credit has landed', function () {
    [, $invoice] = payableInvoiceContext();
    CreditNote::factory()->forInvoice($invoice, 1000)->issued()->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

it('adds several credit notes together', function () {
    [, $invoice] = payableInvoiceContext();

    CreditNote::factory()->forInvoice($invoice, 1000)->issued()->create();
    CreditNote::factory()->forInvoice($invoice, 1500)->issued()->create();

    expect(app(InvoiceSettlement::class)->amountCredited($invoice))->toBe(2500.0);
});
