<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\InvoicePayment;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * One receipt of each kind in the same company, which is the state the register
 * exists to make sense of.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Invoice, 2: \App\Models\Client}
 */
function registerWithBothKinds(): array
{
    [$user, $invoice] = payableInvoiceContext(5000);
    $client = Client::query()->where('company_id', $user->current_company_id)->first();
    $client->update(['name' => 'Riverside Lodge Limited']);

    test()->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 2000,
        'paid_on' => '2026-07-10',
        'method' => 'bank_transfer',
    ])->assertSessionHasNoErrors();

    test()->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($client, 750))
        ->assertSessionHasNoErrors();

    return [$user, $invoice, $client];
}

it('lists both kinds of receipt', function () {
    [$user, $invoice] = registerWithBothKinds();

    $this->actingAs($user)
        ->get('/receipts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Receipts/Index')
            ->where('hasActiveCompany', true)
            ->has('receipts', 2)

            /** Newest paid_on first: the standalone one is dated later. */
            ->where('receipts.0.number', 'RCP-000002')
            ->where('receipts.0.invoice_number', null)
            ->where('receipts.0.amount', 750)
            ->where('receipts.0.description', 'Deposit on borehole installation')

            ->where('receipts.1.number', 'RCP-000001')
            ->where('receipts.1.invoice_number', $invoice->number)

            /** The row links straight to the invoice, so it carries its id. */
            ->where('receipts.1.invoice_id', $invoice->id)
            ->where('receipts.1.amount', 2000)
        );
});

/**
 * Both kinds have to name a client, and they reach one by different routes —
 * the standalone one directly, the invoice-backed one through its invoice.
 */
it('names the client on both kinds', function () {
    [$user] = registerWithBothKinds();

    $this->actingAs($user)
        ->get('/receipts')
        ->assertInertia(fn ($page) => $page
            ->where('receipts.0.client_name', 'Riverside Lodge Limited')
            ->where('receipts.1.client_name', 'Riverside Lodge Limited')
        );
});

it('shows an empty register when there is no active company', function () {
    $user = App\Models\User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get('/receipts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Receipts/Index')
            ->where('hasActiveCompany', false)
            ->has('receipts', 0)
        );
});

it('shows a standalone receipt with no invoice attached', function () {
    [$user] = registerWithBothKinds();
    $receipt = InvoicePayment::query()->standalone()->first();

    $this->actingAs($user)
        ->get("/receipts/{$receipt->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Receipts/show')
            ->where('receipt.number', 'RCP-000002')
            ->where('receipt.invoice', null)
            ->where('receipt.balance_after', null)
            ->where('receipt.client.name', 'Riverside Lodge Limited')
        );
});

it('shows an invoice-backed receipt with its invoice and frozen balance', function () {
    [$user, $invoice] = registerWithBothKinds();
    $receipt = InvoicePayment::query()->againstInvoice()->first();

    $this->actingAs($user)
        ->get("/receipts/{$receipt->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('receipt.number', 'RCP-000001')
            ->where('receipt.invoice.number', $invoice->number)
            ->where('receipt.balance_after', 3000)
        );
});

/**
 * Deleting money out of the ledger has to put the invoice status back where the
 * remaining ledger says it belongs, which is why this route goes through the
 * recorder rather than deleting the row itself.
 */
it('re-derives the invoice status when an invoice-backed receipt is removed', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 5000,
        'paid_on' => '2026-07-10',
        'method' => 'cash',
    ])->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');

    $receipt = InvoicePayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete("/receipts/{$receipt->id}")
        ->assertRedirect('/receipts');

    expect($invoice->fresh()->status)->toBe('sent')
        ->and(InvoicePayment::query()->count())->toBe(0);
});

it('removes a standalone receipt without touching any invoice', function () {
    [$user, $invoice] = registerWithBothKinds();
    $receipt = InvoicePayment::query()->standalone()->first();

    $this->actingAs($user)
        ->delete("/receipts/{$receipt->id}")
        ->assertRedirect('/receipts');

    expect(InvoicePayment::query()->standalone()->count())->toBe(0)
        ->and(InvoicePayment::query()->againstInvoice()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe('partially_paid');
});

/**
 * The delete path frees a number, and the next receipt must not walk back over
 * it — the unique index would throw mid-transaction.
 */
it('does not reissue a number freed by a deletion', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/receipts', standaloneReceiptPayload($client));
    $this->actingAs($user)->post('/receipts', standaloneReceiptPayload($client));

    $second = InvoicePayment::query()->latest('id')->first();
    $this->actingAs($user)->delete("/receipts/{$second->id}");

    $this->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($client))
        ->assertSessionHasNoErrors();

    expect(InvoicePayment::query()->latest('id')->first()->receipt_number)
        ->toBe('RCP-000002');
});

it('offers the create form with the company clients and payment methods', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->get('/receipts/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Receipts/Create')
            ->has('clients', 1)
            ->where('clients.0.id', $client->id)
            ->where('defaultCurrencyCode', 'ZMW')
            ->has('paymentMethods', 6)
            ->where('paymentMethods.0.value', 'cash')
            ->where('paymentMethods.1.label', 'Bank transfer')
        );
});
