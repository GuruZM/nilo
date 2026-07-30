<?php

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Currency;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/* ---------------------------- Creation ---------------------------- */

it('creates a credit note against an invoice', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 2000))
        ->assertSessionHasNoErrors();

    $note = CreditNote::query()->latest('id')->first();

    expect($note->invoice_id)->toBe($invoice->id)
        ->and($note->client_id)->toBe($invoice->client_id)
        ->and($note->company_id)->toBe($invoice->company_id)
        ->and((float) $note->total)->toBe(2000.0)
        ->and($note->number)->toBe('CRN-000001');
});

it('numbers credit notes sequentially per company', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 1000));
    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 1000));

    expect(CreditNote::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['CRN-000001', 'CRN-000002']);
});

it('copies the invoice currency rather than trusting the form', function () {
    [$user, $invoice] = payableInvoiceContext();

    $payload = creditNotePayload($invoice, 1000);
    $payload['currency_code'] = 'USD';

    $this->actingAs($user)->post('/credit-notes', $payload);

    expect(CreditNote::query()->latest('id')->first()->currency_code)->toBe('ZMW');
});

/**
 * The test above would also pass if the controller hardcoded the company
 * default, so this one moves the invoice off ZMW and proves the value really is
 * read from the invoice being credited.
 */
it('follows the invoice when the invoice is not in the company default currency', function () {
    [$user, $invoice] = payableInvoiceContext();

    Currency::firstOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar',
        'symbol' => '$',
        'precision' => 2,
        'is_active' => true,
    ]);

    $invoice->update(['currency_code' => 'USD']);

    $payload = creditNotePayload($invoice, 1000);
    $payload['currency_code'] = 'ZMW';

    $this->actingAs($user)->post('/credit-notes', $payload)->assertSessionHasNoErrors();

    expect(CreditNote::query()->latest('id')->first()->currency_code)->toBe('USD');
});

it('stores the line items', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 2000));
    $note = CreditNote::query()->latest('id')->first();

    $item = CreditNoteItem::query()->where('credit_note_id', $note->id)->first();

    expect($item->description)->toBe('Returned consulting hours')
        ->and((float) $item->line_total)->toBe(2000.0);
});

/* ---------------------------- Money ---------------------------- */

it('carves tax out of the credited amount the same way an invoice does', function () {
    [$user, $invoice] = payableInvoiceContext();

    $payload = creditNotePayload($invoice, 5000);
    $payload['tax_percent'] = 16;

    $this->actingAs($user)->post('/credit-notes', $payload);

    $note = CreditNote::query()->latest('id')->first();

    expect((float) $note->total)->toBe(5000.0)
        ->and((float) $note->subtotal)->toBe(4310.34)
        ->and((float) $note->tax_total)->toBe(689.66);
});

/* ---------------------------- Guards ---------------------------- */

it('refuses to credit more than the invoice still owes', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 6000))
        ->assertSessionHasErrors('items');

    expect(CreditNote::query()->count())->toBe(0);
});

it('allows a credit for exactly the outstanding balance', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 5000))
        ->assertSessionHasNoErrors();

    expect(CreditNote::query()->count())->toBe(1);
});

it('refuses a credit a single cent over the outstanding balance', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 5000.01))
        ->assertSessionHasErrors('items');

    expect(CreditNote::query()->count())->toBe(0);
});

it('accounts for money already paid when capping the credit', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-10', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 1500))
        ->assertSessionHasErrors('items');

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 1000))
        ->assertSessionHasNoErrors();
});

it('lets a draft credit note exceed the balance, since it is not applied yet', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($invoice, 6000, 'draft'))
        ->assertSessionHasNoErrors();

    expect(CreditNote::query()->count())->toBe(1);
});

it('refuses an invoice belonging to another company', function () {
    [$user] = payableInvoiceContext();
    [, $foreignInvoice] = payableInvoiceContext();

    $this->actingAs($user)
        ->post('/credit-notes', creditNotePayload($foreignInvoice, 100))
        ->assertSessionHasErrors('invoice_id');
});

/* ---------------------------- Effect ---------------------------- */

it('settles the invoice when the credit clears the balance', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000));

    expect($invoice->fresh()->status)->toBe('paid');
});

it('leaves the invoice alone for a draft credit note', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000, 'draft'));

    expect($invoice->fresh()->status)->toBe('sent');
});

it('re-settles the invoice when a draft is later issued', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000, 'draft'));
    $note = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/credit-notes/{$note->id}/status", ['status' => 'issued'])
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('re-opens the invoice when an issued credit note is voided', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000));
    $note = CreditNote::query()->latest('id')->first();

    expect($invoice->fresh()->status)->toBe('paid');

    $this->actingAs($user)->post("/credit-notes/{$note->id}/status", ['status' => 'void']);

    expect($invoice->fresh()->status)->toBe('sent');
});

/**
 * A draft was never subtracted from the balance, so issuing it must not be
 * credited with its own total as extra headroom. If `guardCreditFits()` added
 * the note back regardless of its current status, the 5,000 draft below would
 * see 6,000 of room instead of the 1,000 that is really left, and would issue.
 */
it('gives a draft no headroom of its own when it is issued', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000, 'draft'));
    $note = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-10', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->post("/credit-notes/{$note->id}/status", ['status' => 'issued'])
        ->assertSessionHasErrors('items');

    expect($note->fresh()->status)->toBe('draft')
        ->and($invoice->fresh()->status)->toBe('partially_paid');
});

/**
 * Voiding releases the credit, so the balance it used to cover can be settled
 * some other way. Re-issuing must therefore be checked against the balance as
 * it stands now, not as it stood when the note was written.
 */
it('re-checks the balance before letting a voided credit note be issued again', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 5000));
    $note = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)->post("/credit-notes/{$note->id}/status", ['status' => 'void']);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-10', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->post("/credit-notes/{$note->id}/status", ['status' => 'issued'])
        ->assertSessionHasErrors('items');

    expect($note->fresh()->status)->toBe('void')
        ->and($invoice->fresh()->status)->toBe('partially_paid');
});

/* ---------------------------- Rendering ---------------------------- */

it('renders a saved credit note through the shared sheet', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 2000));
    $note = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/credit-notes/{$note->id}/preview")
        ->assertSuccessful()
        ->assertSee('CREDIT NOTE', false);
});

it('will not preview a credit note from another company', function () {
    [$user] = payableInvoiceContext();
    [$otherUser, $foreignInvoice] = payableInvoiceContext();

    $this->actingAs($otherUser)->post('/credit-notes', creditNotePayload($foreignInvoice, 100));
    $foreignNote = CreditNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/credit-notes/{$foreignNote->id}/preview")
        ->assertForbidden();
});

/* ---------------------------- Index ---------------------------- */

it('lists credit notes for the active company', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post('/credit-notes', creditNotePayload($invoice, 1000));

    $this->actingAs($user)
        ->get('/credit-notes')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            /**
             * The second argument turns off the page-file existence check.
             * `inertia.testing.ensure_pages_exist` is true in this application,
             * and `resources/js/pages/CreditNotes/Index.tsx` is built in task
             * 2.5, not here. Everything this test is actually about — the
             * component name and the props behind it — is still asserted. Drop
             * the `false` once the page exists.
             */
            ->component('CreditNotes/Index', false)
            ->has('creditNotes', 1)
            ->where('creditNotes.0.number', 'CRN-000001')
        );
});
