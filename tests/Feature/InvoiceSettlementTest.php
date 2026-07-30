<?php

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoiceSettlement;

/**
 * An invoice for exactly 5,000 in the active company, so every assertion below
 * reads as plain arithmetic.
 */
function invoiceForSettlement(float $total = 5000.0, string $status = 'sent'): Invoice
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    return Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000001',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => $status,
    ]);
}

it('reports nothing paid on a fresh invoice', function () {
    $invoice = invoiceForSettlement();
    $settlement = app(InvoiceSettlement::class);

    expect($settlement->amountPaid($invoice))->toBe(0.0)
        ->and($settlement->balanceDue($invoice))->toBe(5000.0);
});

it('subtracts a part payment from the balance', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 2000)->create();

    $settlement = app(InvoiceSettlement::class);

    expect($settlement->amountPaid($invoice->fresh()))->toBe(2000.0)
        ->and($settlement->balanceDue($invoice->fresh()))->toBe(3000.0);
});

it('adds multiple payments together', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 2000)->create();
    InvoicePayment::factory()->forInvoice($invoice, 1500)->create();

    expect(app(InvoiceSettlement::class)->amountPaid($invoice->fresh()))->toBe(3500.0);
});

it('never reports a negative balance', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 6000)->create();

    expect(app(InvoiceSettlement::class)->balanceDue($invoice->fresh()))->toBe(0.0);
});

it('moves an invoice to partially paid on a part payment', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 2000)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

it('moves an invoice to paid when the balance clears', function () {
    $invoice = invoiceForSettlement();
    InvoicePayment::factory()->forInvoice($invoice, 5000)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('paid');
});

it('returns an invoice to sent when its only payment is removed', function () {
    $invoice = invoiceForSettlement();
    $payment = InvoicePayment::factory()->forInvoice($invoice, 5000)->create();

    $settlement = app(InvoiceSettlement::class);
    $settlement->sync($invoice);
    expect($invoice->fresh()->status)->toBe('paid');

    $payment->delete();
    $settlement->sync($invoice->fresh());

    expect($invoice->fresh()->status)->toBe('sent');
});

it('leaves an unissued draft alone', function () {
    $invoice = invoiceForSettlement(status: 'draft');

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('draft');
});

it('leaves a pending invoice pending when nothing has been paid', function () {
    $invoice = invoiceForSettlement(status: 'pending');

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('pending');
});

it('leaves a voided invoice alone', function () {
    $invoice = invoiceForSettlement(status: 'void');
    InvoicePayment::factory()->forInvoice($invoice, 5000)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('void');
});

it('rounds to two places so a cent never blocks settlement', function () {
    $invoice = invoiceForSettlement(333.33);
    InvoicePayment::factory()->forInvoice($invoice, 333.33)->create();

    app(InvoiceSettlement::class)->sync($invoice);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(app(InvoiceSettlement::class)->balanceDue($invoice->fresh()))->toBe(0.0);
});

/**
 * These three amounts are not arbitrary. Summed as binary floats they land on
 * 1000.3299999999999, roughly 1.1e-13 short of the invoice total, so without
 * the rounding in `amountPaid()` the invoice is left a fraction of a ngwei
 * unpaid and never reaches `paid`. A split that happens to be exactly
 * representable — 5000.00 as 1666.67 + 1666.67 + 1666.66, say — passes either
 * way and guards nothing.
 */
it('settles an invoice split across payments that do not divide evenly', function () {
    $invoice = invoiceForSettlement(1000.33);

    foreach ([333.45, 333.44, 333.44] as $amount) {
        InvoicePayment::factory()->forInvoice($invoice, $amount)->create();
    }

    $settlement = app(InvoiceSettlement::class);
    $settlement->sync($invoice);

    expect($settlement->amountPaid($invoice->fresh()))->toBe(1000.33)
        ->and($settlement->balanceDue($invoice->fresh()))->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid');
});
