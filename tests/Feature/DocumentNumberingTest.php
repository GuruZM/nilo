<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Currency::create([
        'code' => 'ZMW',
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);

    Mail::fake();
});

/* ---------------------------- Invoices ---------------------------- */

it('numbers the first invoice of a company one', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHasNoErrors();

    expect(Invoice::query()->latest('id')->first()->number)->toBe('INV-000001');
});

/** The worked example: two invoices already issued, so the third is 3. */
it('numbers each new invoice one past the highest already issued', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $numbers = collect(range(1, 3))->map(function () use ($user, $client, $template) {
        $this->actingAs($user)
            ->post('/invoices', invoicePayload($client, $template, false))
            ->assertSessionHasNoErrors();

        return Invoice::query()->latest('id')->first()->number;
    });

    expect($numbers->all())->toBe(['INV-000001', 'INV-000002', 'INV-000003']);
});

it('keeps invoice numbers independent per company', function () {
    [$first, $firstClient, $firstTemplate] = invoiceCreationContext('client@example.com');
    [$second, $secondClient, $secondTemplate] = invoiceCreationContext('other@example.com');

    $this->actingAs($first)
        ->post('/invoices', invoicePayload($firstClient, $firstTemplate, false))
        ->assertSessionHasNoErrors();

    $this->actingAs($second)
        ->post('/invoices', invoicePayload($secondClient, $secondTemplate, false))
        ->assertSessionHasNoErrors();

    expect(Invoice::query()->where('company_id', $second->current_company_id)->value('number'))
        ->toBe('INV-000001');
});

/**
 * Invoices are deletable, so a row count would walk the sequence back over a
 * number that still exists and collide with the unique index.
 */
it('never reissues an invoice number after a deletion', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)
            ->post('/invoices', invoicePayload($client, $template, false))
            ->assertSessionHasNoErrors();
    }

    /** Delete the first two; INV-000003 survives. A row count would say 2. */
    Invoice::query()->whereIn('number', ['INV-000001', 'INV-000002'])->delete();

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHasNoErrors();

    expect(Invoice::query()->latest('id')->first()->number)->toBe('INV-000004');
});

it('numbers an invoice created through the mobile API on the same sequence', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHasNoErrors();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/invoices', invoicePayload($client, $template, false))
        ->assertSuccessful();

    expect(Invoice::query()->latest('id')->first()->number)->toBe('INV-000002');
});

/* ---------------------------- Backfill ---------------------------- */

/**
 * Stores an invoice with no number, the shape every invoice had before there
 * was invoice numbering.
 */
function numberlessInvoice(int $companyId, int $clientId): Invoice
{
    return Invoice::query()->create([
        'company_id' => $companyId,
        'client_id' => $clientId,
        'number' => null,
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'subtotal' => 100,
        'total' => 100,
        'status' => 'sent',
    ]);
}

function runInvoiceNumberBackfill(): void
{
    (require database_path('migrations/2026_08_08_101750_backfill_invoice_numbers.php'))->up();
}

it('numbers invoices that predate invoice numbering, oldest first', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $invoices = collect(range(1, 3))->map(
        fn () => numberlessInvoice((int) $user->current_company_id, $client->id)
    );

    runInvoiceNumberBackfill();

    expect($invoices->map(fn (Invoice $invoice) => $invoice->fresh()->number)->all())
        ->toBe(['INV-000001', 'INV-000002', 'INV-000003']);
});

it('backfills past the numbers a company has already issued', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHasNoErrors();

    $old = numberlessInvoice((int) $user->current_company_id, $client->id);

    runInvoiceNumberBackfill();

    expect($old->fresh()->number)->toBe('INV-000002');
});

it('backfills each company onto its own sequence', function () {
    [$first, $firstClient] = invoiceCreationContext('client@example.com');
    [$second, $secondClient] = invoiceCreationContext('other@example.com');

    $a = numberlessInvoice((int) $first->current_company_id, $firstClient->id);
    $b = numberlessInvoice((int) $second->current_company_id, $secondClient->id);

    runInvoiceNumberBackfill();

    expect($a->fresh()->number)->toBe('INV-000001')
        ->and($b->fresh()->number)->toBe('INV-000001');
});

it('leaves invoices that already carry a number alone', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHasNoErrors();

    $invoice = Invoice::query()->latest('id')->first();
    $touchedAt = $invoice->updated_at;

    runInvoiceNumberBackfill();

    expect($invoice->fresh()->number)->toBe('INV-000001')
        ->and($invoice->fresh()->updated_at->eq($touchedAt))->toBeTrue();
});

/* ---------------------------- Quotations ---------------------------- */

it('numbers each new quotation one past the highest already issued', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $numbers = collect(range(1, 3))->map(function () use ($user, $client, $template) {
        $this->actingAs($user)
            ->post('/quotations', quotationPayload($client, $template, false))
            ->assertSessionHasNoErrors();

        return Quotation::query()->latest('id')->first()->number;
    });

    expect($numbers->all())->toBe(['QUO-000001', 'QUO-000002', 'QUO-000003']);
});

/**
 * The row count this replaced could not tell a quotation apart from anything
 * else in the table; the prefixed scan can.
 */
it('continues the quotation sequence from the highest number, not the row count', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    Quotation::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'number' => 'QUO-000009',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, false))
        ->assertSessionHasNoErrors();

    expect(Quotation::query()->latest('id')->first()->number)->toBe('QUO-000010');
});
