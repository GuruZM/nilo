<?php

use App\Models\Client;
use App\Models\Company;
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

it('issues a receipt with no invoice behind it', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($client, 1500))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $receipt = InvoicePayment::query()->latest('id')->first();

    expect($receipt->invoice_id)->toBeNull()
        ->and($receipt->client_id)->toBe($client->id)
        ->and($receipt->company_id)->toBe($user->current_company_id)
        ->and($receipt->recorded_by)->toBe($user->id)
        ->and($receipt->receipt_number)->toBe('RCP-000001')
        ->and((float) $receipt->amount)->toBe(1500.0)
        ->and($receipt->description)->toBe('Deposit on borehole installation');
});

/**
 * The balance is what an invoice was left owing. A receipt that settles no
 * invoice has no such figure, and stamping one would be a fabrication.
 */
it('leaves the frozen balance null on a standalone receipt', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/receipts', standaloneReceiptPayload($client));

    expect(InvoicePayment::query()->latest('id')->first()->balance_after)->toBeNull();
});

/**
 * The load-bearing consequence of keeping both kinds in one table: they share a
 * single RCP- sequence. If they ever forked, the second source would hand out a
 * number the first had already used and collide on the unique index.
 */
it('continues the same number sequence as invoice-backed receipts', function () {
    [$user, $invoice] = payableInvoiceContext(5000);
    $client = Client::query()->where('company_id', $user->current_company_id)->first();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 1000,
        'paid_on' => '2026-07-10',
        'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($client))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 500,
        'paid_on' => '2026-07-12',
        'method' => 'cash',
    ])->assertSessionHasNoErrors();

    expect(InvoicePayment::query()->orderBy('id')->pluck('receipt_number')->all())
        ->toBe(['RCP-000001', 'RCP-000002', 'RCP-000003']);
});

it('does not touch any invoice status', function () {
    [$user, $invoice] = payableInvoiceContext(5000);
    $client = Client::query()->where('company_id', $user->current_company_id)->first();

    $this->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($client, 5000))
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('sent');
});

it('rejects a client belonging to another company', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $stranger = Client::factory()->create([
        'company_id' => Company::factory()->create()->id,
    ]);

    $this->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($stranger))
        ->assertSessionHasErrors('client_id');

    expect(InvoicePayment::query()->count())->toBe(0);
});

it('refuses an amount of zero', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/receipts', [...standaloneReceiptPayload($client), 'amount' => 0])
        ->assertSessionHasErrors('amount');
});

/**
 * Unlike a payment against an invoice, nothing caps a standalone receipt: there
 * is no balance for it to exceed.
 */
it('accepts an amount larger than any invoice', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/receipts', standaloneReceiptPayload($client, 999999))
        ->assertSessionHasNoErrors();

    expect((float) InvoicePayment::query()->latest('id')->first()->amount)->toBe(999999.0);
});

it('keeps another company out of a receipt it does not own', function () {
    [$owner, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($owner)->post('/receipts', standaloneReceiptPayload($client));
    $receipt = InvoicePayment::query()->latest('id')->first();

    $outsider = User::factory()->withSubscription()->create();
    $otherCompany = Company::factory()->create();
    $outsider->companies()->attach($otherCompany->id, ['is_owner' => true, 'status' => 'active']);
    $outsider->forceFill(['current_company_id' => $otherCompany->id])->save();

    $this->actingAs($outsider)->get("/receipts/{$receipt->id}")->assertForbidden();
    $this->actingAs($outsider)->get("/receipts/{$receipt->id}/print")->assertForbidden();
    $this->actingAs($outsider)->delete("/receipts/{$receipt->id}")->assertForbidden();
});

it('prints a standalone receipt through the shared sheet', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');
    $client->update(['name' => 'Riverside Lodge Limited']);

    $this->actingAs($user)->post('/receipts', standaloneReceiptPayload($client, 1500));
    $receipt = InvoicePayment::query()->latest('id')->first();

    $response = $this->actingAs($user)->get("/receipts/{$receipt->id}/print");

    $response->assertOk();
    expect($response->getContent())
        ->toContain('RECEIPT')
        ->toContain('RCP-000001')
        ->toContain('Riverside Lodge Limited')
        ->toContain('Deposit on borehole installation')

        /** No invoice, so the type's own closing line stands in for the balance. */
        ->toContain('Payment received with thanks.');
});

/**
 * The description is the whole printed line on a standalone receipt, so leaving
 * it out must still produce a line rather than an empty one.
 */
it('falls back to generic wording when no description is given', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/receipts', [
        ...standaloneReceiptPayload($client),
        'description' => null,
    ])->assertSessionHasNoErrors();

    $receipt = InvoicePayment::query()->latest('id')->first();

    expect($receipt->printableItems()->first()->description)
        ->toBe('Payment received (Cash)');
});

it('addresses an invoice-backed receipt through its invoice', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 5000,
        'paid_on' => '2026-07-10',
        'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $receipt = InvoicePayment::query()->latest('id')->first();

    expect($receipt->client_id)->toBeNull()
        ->and($receipt->counterparty()->id)->toBe($invoice->client_id)
        ->and($receipt->printableItems()->first()->description)
        ->toBe('Payment for invoice INV-000001 (Cash)');
});

/**
 * Guards the notes attribute against the standalone branch swallowing the
 * frozen balance that invoice-backed receipts have always printed.
 */
it('still prints the frozen balance on an invoice-backed receipt', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 2000,
        'paid_on' => '2026-07-10',
        'method' => 'bank_transfer',
    ])->assertSessionHasNoErrors();

    $receipt = InvoicePayment::query()->latest('id')->first();

    expect((float) $receipt->balance_after)->toBe(3000.0)
        ->and($receipt->notes)->toContain('Balance remaining on invoice INV-000001: 3,000.00 ZMW');

    /** Recording it later must not rewrite what the client was handed. */
    Invoice::query()->whereKey($invoice->id)->update(['total' => 9000]);

    expect($receipt->fresh()->notes)->toContain('3,000.00 ZMW');
});
