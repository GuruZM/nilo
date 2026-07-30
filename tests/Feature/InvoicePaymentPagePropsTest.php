<?php

use App\Models\Currency;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

it('sends the balance and an empty ledger for an unpaid invoice', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/show')
            ->where('invoice.amount_paid', 0)
            ->where('invoice.balance_due', 5000)
            ->has('invoice.payments', 0)
        );
});

it('sends each recorded payment with its receipt number', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 2000, 'paid_on' => '2026-07-15', 'method' => 'mobile_money',
    ]);

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('invoice.amount_paid', 2000)
            ->where('invoice.balance_due', 3000)
            ->has('invoice.payments', 1)
            ->where('invoice.payments.0.receipt_number', 'RCP-000001')
            ->where('invoice.payments.0.amount', 2000)
            ->where('invoice.payments.0.method', 'mobile_money')
            ->where('invoice.payments.0.method_label', 'Mobile money')
        );
});

it('sends the payment methods the form offers', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page->has('paymentMethods', 6));
});

/**
 * The ledger reads as a statement: the most recent receipt first, so the
 * newest money is not buried under the history.
 */
it('sends the ledger newest first', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 1000, 'paid_on' => '2026-07-05', 'method' => 'cash',
    ]);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 1500, 'paid_on' => '2026-07-20', 'method' => 'cheque',
    ]);

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page
            ->has('invoice.payments', 2)
            ->where('invoice.payments.0.paid_on', '2026-07-20')
            ->where('invoice.payments.0.amount', 1500)
            ->where('invoice.payments.1.paid_on', '2026-07-05')
            ->where('invoice.payments.1.amount', 1000)
            ->where('invoice.amount_paid', 2500)
            ->where('invoice.balance_due', 2500)
        );
});

/**
 * `payments.recorder:id,name` selects two columns; the local key the ledger
 * matches on must survive that narrowing or every row reports no recorder.
 */
it('names who recorded each payment', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 500, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page
            ->where('invoice.payments.0.recorded_by', $user->name)
        );
});
