<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;

/**
 * Money received against an invoice.
 *
 * The rules that matter here are the ones that keep the ledger honest: a
 * payment cannot exceed what is owed, its currency comes from the invoice
 * rather than the request, and the invoice status is derived from the ledger
 * rather than written by hand.
 */
function apiPayableInvoice(float $total = 5000): array
{
    [$user, $client] = apiContext();

    $invoice = Invoice::factory()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'currency_code' => 'ZMW',
        'subtotal' => $total,
        'total' => $total,
        'status' => 'sent',
    ]);

    return [$user, $invoice];
}

it('records a payment and returns its receipt', function () {
    [$user, $invoice] = apiPayableInvoice();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 2000,
            'paid_on' => '2026-07-05',
            'method' => 'mobile_money',
            'reference' => 'MTN-9931',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.method', 'mobile_money')
        ->assertJsonPath('data.currency_code', 'ZMW');

    expect($response->json('data.receipt_number'))->toStartWith('RCP-')
        ->and((float) $response->json('data.amount'))->toBe(2000.00)
        ->and((float) $response->json('data.balance_after'))->toBe(3000.00);
});

/**
 * The status is never written by the payment endpoint — InvoiceSettlement
 * derives it, so recording and removing cannot disagree.
 */
it('derives the invoice status from the ledger as payments land', function () {
    [$user, $invoice] = apiPayableInvoice();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 2000, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ])->assertCreated();

    expect($invoice->fresh()->status)->toBe('partially_paid');

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 3000, 'paid_on' => '2026-07-06', 'method' => 'cash',
        ])->assertCreated();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('refuses a payment larger than the balance outstanding', function () {
    [$user, $invoice] = apiPayableInvoice();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 6000, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('amount');

    expect($response->json('errors.amount.0'))->toContain('5,000.00')
        ->and(InvoicePayment::count())->toBe(0);
});

it('says an invoice is already settled rather than quoting a zero cap', function () {
    [$user, $invoice] = apiPayableInvoice();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 5000, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ])->assertCreated();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 100, 'paid_on' => '2026-07-06', 'method' => 'cash',
        ]);

    $response->assertStatus(422);

    expect($response->json('errors.amount.0'))->toContain('already settled in full');
});

it('rejects a payment method the ledger does not use', function () {
    [$user, $invoice] = apiPayableInvoice();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 100, 'paid_on' => '2026-07-05', 'method' => 'goats',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('method');
});

/**
 * A receipt is denominated by the invoice it settles, never by the request —
 * otherwise a foreign figure would be subtracted from a local balance.
 */
it('ignores any currency the client tries to send', function () {
    [$user, $invoice] = apiPayableInvoice();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 1000, 'paid_on' => '2026-07-05', 'method' => 'cash',
            'currency_code' => 'USD',
        ])
        ->assertCreated()
        ->assertJsonPath('data.currency_code', 'ZMW');
});

it('lists the payments on an invoice', function () {
    [$user, $invoice] = apiPayableInvoice();

    foreach ([1000, 1500] as $index => $amount) {
        $this->withHeaders(apiHeaders($user))
            ->postJson(route('api.v1.invoices.payments.store', $invoice), [
                'amount' => $amount, 'paid_on' => '2026-07-0'.($index + 5), 'method' => 'cash',
            ])->assertCreated();
    }

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.payments.index', $invoice))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('reverses the invoice status when a payment is removed', function () {
    [$user, $invoice] = apiPayableInvoice();

    $payment = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 5000, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ]);

    expect($invoice->fresh()->status)->toBe('paid');

    $this->withHeaders(apiHeaders($user))
        ->deleteJson(route('api.v1.invoices.payments.destroy', [$invoice, $payment->json('data.id')]))
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('sent')
        ->and(InvoicePayment::count())->toBe(0);
});

/* ------------------------------------------------------------- Tenancy -- */

it('refuses to pay an invoice belonging to another company', function () {
    [$user] = apiContext();

    $stranger = Company::factory()->create();
    $invoice = Invoice::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => Client::factory()->create(['company_id' => $stranger->id])->id,
        'total' => 1000,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 100, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ])
        ->assertForbidden();

    expect(InvoicePayment::count())->toBe(0);
});

/**
 * Route model binding resolves both by id, so a receipt from one invoice could
 * otherwise be reached through another.
 */
it('refuses to remove a payment through an invoice it was not recorded against', function () {
    [$user, $invoice] = apiPayableInvoice();

    $other = Invoice::factory()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $invoice->client_id,
        'total' => 1000,
    ]);

    $payment = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 1000, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ]);

    $this->withHeaders(apiHeaders($user))
        ->deleteJson(route('api.v1.invoices.payments.destroy', [$other, $payment->json('data.id')]))
        ->assertForbidden();

    expect(InvoicePayment::count())->toBe(1);
});

it('serves the receipt as a pdf', function () {
    [$user, $invoice] = apiPayableInvoice();

    $payment = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.payments.store', $invoice), [
            'amount' => 1000, 'paid_on' => '2026-07-05', 'method' => 'cash',
        ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->get(route('api.v1.invoices.payments.pdf', [$invoice, $payment->json('data.id')]));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});
