<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * Posts one payment against an invoice as the given user.
 *
 * @param  array<string, mixed>  $overrides
 */
function recordPayment(User $user, Invoice $invoice, float $amount, array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs($user)->post(
        route('invoices.payments.store', $invoice),
        array_merge([
            'amount' => $amount,
            'paid_on' => '2026-07-15',
            'method' => 'mobile_money',
            'reference' => 'TX-1',
        ], $overrides),
    );
}

/* ---------------------------- Recording ---------------------------- */

it('records a payment against an invoice', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();

    $payment = InvoicePayment::query()->latest('id')->first();

    expect((float) $payment->amount)->toBe(2000.0)
        ->and($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->company_id)->toBe($invoice->company_id)
        ->and($payment->currency_code)->toBe('ZMW')
        ->and($payment->recorded_by)->toBe($user->id)
        ->and($payment->method)->toBe('mobile_money')
        ->and($payment->reference)->toBe('TX-1');
});

it('takes the currency from the invoice rather than from the request', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000, ['currency_code' => 'USD'])
        ->assertSessionHasNoErrors();

    expect(InvoicePayment::query()->latest('id')->first()->currency_code)->toBe('ZMW');
});

it('stamps the balance the invoice was left with', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    recordPayment($user, $invoice->fresh(), 1500)->assertSessionHasNoErrors();

    $payments = InvoicePayment::query()->orderBy('id')->get();

    expect((float) $payments[0]->balance_after)->toBe(3000.0)
        ->and((float) $payments[1]->balance_after)->toBe(1500.0);
});

it('numbers receipts sequentially within a company', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    recordPayment($user, $invoice->fresh(), 1500)->assertSessionHasNoErrors();

    expect(InvoicePayment::query()->orderBy('id')->pluck('receipt_number')->all())
        ->toBe(['RCP-000001', 'RCP-000002']);
});

it('starts each company at its own first receipt number', function () {
    [$firstUser, $firstInvoice] = payableInvoiceContext(5000);
    [$secondUser, $secondInvoice] = payableInvoiceContext(5000);

    recordPayment($firstUser, $firstInvoice, 2000)->assertSessionHasNoErrors();
    recordPayment($secondUser, $secondInvoice, 2000)->assertSessionHasNoErrors();

    expect(InvoicePayment::query()->where('invoice_id', $secondInvoice->id)->value('receipt_number'))
        ->toBe('RCP-000001');
});

/* ---------------------------- Derived status ---------------------------- */

it('moves the invoice to partially paid on a part payment', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
});

it('moves the invoice to paid once the balance clears', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    recordPayment($user, $invoice->fresh(), 3000)->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

/* ---------------------------- Refusals ---------------------------- */

it('refuses a payment larger than the invoice', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 6000)->assertSessionHasErrors('amount');

    expect(InvoicePayment::query()->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe('sent');
});

it('refuses a payment that would overshoot what is left after an earlier one', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 4000)->assertSessionHasNoErrors();
    recordPayment($user, $invoice->fresh(), 1500)->assertSessionHasErrors('amount');

    expect(InvoicePayment::query()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
});

it('refuses any further payment on an invoice already settled in full', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 5000)->assertSessionHasNoErrors();

    /**
     * The balance is now zero, so the max rule is `max:0`. It must refuse in
     * plain words rather than emitting an absurd figure.
     */
    $response = recordPayment($user, $invoice->fresh(), 100);

    $response->assertSessionHasErrors('amount');

    expect(session('errors')->first('amount'))->toContain('settled in full')
        ->and(InvoicePayment::query()->count())->toBe(1);
});

it('names the outstanding figure when the payment is too large', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    recordPayment($user, $invoice->fresh(), 4000)->assertSessionHasErrors('amount');

    expect(session('errors')->first('amount'))->toContain('3,000.00');
});

it('rejects an unknown payment method', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 1000, ['method' => 'crypto'])
        ->assertSessionHasErrors('method');

    expect(InvoicePayment::query()->count())->toBe(0);
});

it('rejects an amount that is not positive money', function (float $amount) {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, $amount)->assertSessionHasErrors('amount');

    expect(InvoicePayment::query()->count())->toBe(0);
})->with([
    'zero' => 0.0,
    'negative' => -100.0,
]);

it('refuses to pay an invoice belonging to another company', function () {
    [$user] = payableInvoiceContext(5000);
    [, $otherInvoice] = payableInvoiceContext(5000);

    recordPayment($user, $otherInvoice, 1000)->assertForbidden();

    expect(InvoicePayment::query()->count())->toBe(0);
});

/* ---------------------------- Removing ---------------------------- */

it('reverses the invoice status when a payment is removed', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete(route('invoices.payments.destroy', [$invoice, $payment]))
        ->assertSessionHasNoErrors();

    expect(InvoicePayment::query()->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe('sent');
});

it('refuses to remove a payment recorded against another invoice', function () {
    [$user, $invoice] = payableInvoiceContext(5000);
    [, $otherInvoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete(route('invoices.payments.destroy', [$otherInvoice, $payment]))
        ->assertForbidden();

    expect(InvoicePayment::query()->count())->toBe(1);
});

/**
 * A second invoice in the same company as `$invoice`, so the payment guards can
 * be tested where the company check alone would let the request through.
 */
function siblingInvoice(Invoice $invoice): Invoice
{
    return Invoice::query()->create([
        'company_id' => $invoice->company_id,
        'client_id' => $invoice->client_id,
        'invoice_template_id' => $invoice->invoice_template_id,
        'number' => 'INV-000002',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => 5000,
        'total' => 5000,
        'status' => 'sent',
    ]);
}

it('refuses to remove a payment through an invoice it was not recorded against', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    $payment = InvoicePayment::query()->latest('id')->first();

    /** Same company, so only the invoice_id guard stands between the two. */
    $this->actingAs($user)
        ->delete(route('invoices.payments.destroy', [siblingInvoice($invoice), $payment]))
        ->assertForbidden();

    expect(InvoicePayment::query()->count())->toBe(1);
});

/* ---------------------------- Printing ---------------------------- */

it('prints a receipt for a recorded payment', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->get(route('invoices.payments.print', [$invoice, $payment]))
        ->assertOk()
        ->assertSee('RECEIPT', false)
        ->assertSee('RCP-000001', false);
});

it('refuses to print a receipt through an invoice it was not recorded against', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    recordPayment($user, $invoice, 2000)->assertSessionHasNoErrors();
    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->get(route('invoices.payments.print', [siblingInvoice($invoice), $payment]))
        ->assertForbidden();
});

it('refuses to print a receipt belonging to another company', function () {
    [$user] = payableInvoiceContext(5000);
    [$otherUser, $otherInvoice] = payableInvoiceContext(5000);

    recordPayment($otherUser, $otherInvoice, 2000)->assertSessionHasNoErrors();
    $payment = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->get(route('invoices.payments.print', [$otherInvoice, $payment]))
        ->assertForbidden();
});
