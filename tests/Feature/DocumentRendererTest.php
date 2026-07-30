<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

function paymentForRendering(float $total = 5000.0, float $amount = 2000.0): InvoicePayment
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $invoice = Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000042',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => 'sent',
    ]);

    return InvoicePayment::factory()->forInvoice($invoice, $amount)->create([
        'receipt_number' => 'RCP-000001',
        'method' => 'mobile_money',
        'paid_on' => '2026-07-15',
    ]);
}

it('renders a payment as a receipt', function () {
    $html = app(DocumentRenderer::class)->html(paymentForRendering());

    expect($html)->toContain('>RECEIPT<')
        ->toContain('RCP-000001')
        ->toContain('Payment received with thanks.');
});

it('names the invoice the money was received against', function () {
    expect(app(DocumentRenderer::class)->html(paymentForRendering()))
        ->toContain('INV-000042');
});

it('prints the amount received rather than the invoice total', function () {
    $html = app(DocumentRenderer::class)->html(paymentForRendering(5000, 2000));

    expect($html)->toContain('2,000.00');
});

it('shows the balance still outstanding on a part payment', function () {
    $html = app(DocumentRenderer::class)->html(paymentForRendering(5000, 2000));

    expect($html)->toContain('3,000.00');
});

it('freezes the balance shown on a receipt at the moment it was issued', function () {
    $payment = paymentForRendering(5000, 2000);
    $payment->update(['balance_after' => 3000]);

    /** A later payment must not rewrite what the first receipt says. */
    App\Models\InvoicePayment::factory()
        ->forInvoice($payment->invoice, 1500)
        ->create(['balance_after' => 1500]);

    expect(app(DocumentRenderer::class)->html($payment->fresh()))
        ->toContain('3,000.00')
        ->not->toContain('1,500.00');
});

it('says an invoice is settled in full when the frozen balance is zero', function () {
    $payment = paymentForRendering(5000, 5000);
    $payment->update(['balance_after' => 0]);

    expect(app(DocumentRenderer::class)->html($payment->fresh()))
        ->toContain('is settled in full');
});

it('falls back to the live balance for a payment recorded before balances were frozen', function () {
    $payment = paymentForRendering(5000, 2000);

    expect($payment->balance_after)->toBeNull()
        ->and(app(DocumentRenderer::class)->html($payment))->toContain('3,000.00');
});

it('provisions a receipt template on first render', function () {
    $payment = paymentForRendering();

    app(DocumentRenderer::class)->html($payment);

    expect(App\Models\InvoiceTemplate::query()
        ->where('company_id', $payment->company_id)
        ->where('type', 'receipt')
        ->exists())->toBeTrue();
});

it('names the downloaded file after the receipt number', function () {
    expect(app(DocumentRenderer::class)->filename(paymentForRendering()))
        ->toBe('RCP-000001.pdf');
});
