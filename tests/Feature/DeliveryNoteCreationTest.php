<?php

use App\Models\Currency;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * An invoice with two priced lines, so the copy-without-prices behaviour has
 * something to prove.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Invoice}
 */
function invoiceWithLines(): array
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    /**
     * The money assertions below check that words like "Price" and "Total"
     * never reach a delivery note. Faker's company names are drawn from a
     * surname list that includes Price, so roughly one run in three hundred
     * printed the client's own name and failed. Fixed names keep those
     * assertions about the template rather than about the fixture.
     */
    $client->update(['name' => 'Bolt Buyers Limited']);
    App\Models\Company::whereKey($user->current_company_id)->update(['name' => 'Steelworks Zambia Limited']);

    $invoice = Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'number' => 'INV-000007',
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => 5000,
        'total' => 5000,
        'status' => 'sent',
    ]);

    $invoice->items()->createMany([
        ['description' => 'Steel bolts', 'unit' => 'box', 'quantity' => 4, 'unit_price' => 750, 'line_total' => 3000, 'sort_order' => 0],
        ['description' => 'Washers', 'unit' => 'box', 'quantity' => 2, 'unit_price' => 1000, 'line_total' => 2000, 'sort_order' => 1],
    ]);

    return [$user, $invoice];
}

it('generates a delivery note from an invoice', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/delivery-note")
        ->assertSessionHasNoErrors();

    $note = DeliveryNote::query()->latest('id')->first();

    expect($note->invoice_id)->toBe($invoice->id)
        ->and($note->client_id)->toBe($invoice->client_id)
        ->and($note->company_id)->toBe($invoice->company_id)
        ->and($note->number)->toBe('DN-000001')
        ->and($note->status)->toBe('draft');
});

it('copies the invoice lines without any prices', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $items = DeliveryNoteItem::query()->where('delivery_note_id', $note->id)->orderBy('sort_order')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->description)->toBe('Steel bolts')
        ->and((float) $items[0]->quantity)->toBe(4.0)
        ->and($items[0]->getAttributes())->not->toHaveKey('unit_price')
        ->and($items[1]->description)->toBe('Washers');
});

/**
 * The lines are a snapshot, not a view. What was dispatched is a fact about a
 * moment in time — re-pricing or re-wording the invoice afterwards must not
 * rewrite the sheet somebody already signed.
 */
it('does not follow the invoice when the invoice is edited afterwards', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $invoice->items()->where('description', 'Steel bolts')->update([
        'description' => 'Brass bolts',
        'quantity' => 99,
    ]);
    $invoice->items()->where('description', 'Washers')->delete();

    $note->refresh();

    expect($note->items)->toHaveCount(2)
        ->and($note->items[0]->description)->toBe('Steel bolts')
        ->and((float) $note->items[0]->quantity)->toBe(4.0)
        ->and($note->items[1]->description)->toBe('Washers');

    expect($this->actingAs($user)->get("/delivery-notes/{$note->id}/preview")->getContent())
        ->toContain('Steel bolts')
        ->not->toContain('Brass bolts');
});

it('flags the invoice as having a delivery note', function () {
    [$user, $invoice] = invoiceWithLines();

    // Read it back from the database: the freshly-created in-memory model has
    // no value for the column at all, so asserting on it would test nothing.
    expect($invoice->fresh()->has_delivery_note)->toBeFalse();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    expect($invoice->fresh()->has_delivery_note)->toBeTrue();
});

it('addresses the delivery to the client by default', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    expect(DeliveryNote::query()->latest('id')->first()->deliver_to)
        ->toBe($invoice->client->name);
});

it('numbers delivery notes sequentially per company', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    expect(DeliveryNote::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['DN-000001', 'DN-000002']);
});

it('refuses an invoice from another company', function () {
    [$user] = invoiceWithLines();
    [, $foreignInvoice] = invoiceWithLines();

    $this->actingAs($user)
        ->post("/invoices/{$foreignInvoice->id}/delivery-note")
        ->assertForbidden();

    expect(DeliveryNote::query()->count())->toBe(0);
});

/* ---------------------------- Rendering ---------------------------- */

it('prints without a single price on it', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    // Positive control: the same lines on the invoice sheet do print their money,
    // so the absence of these strings on the delivery note means something.
    expect($this->actingAs($user)->get("/invoices/{$invoice->id}/preview")->getContent())
        ->toContain('750.00')
        ->toContain('3,000.00')
        ->toContain('GRAND TOTAL');

    $response = $this->actingAs($user)->get("/delivery-notes/{$note->id}/preview");
    $response->assertSuccessful();

    expect($response->getContent())
        ->toContain('DELIVERY NOTE')
        ->toContain('Steel bolts')
        ->not->toContain('750.00')
        ->not->toContain('3,000.00')
        ->not->toContain('GRAND TOTAL')
        // The money columns themselves, not just their contents: a delivery note
        // whose items carry no prices would print an honest-looking 0.00 under
        // these headers, which is worse than printing nothing.
        ->not->toContain('Price')
        ->not->toContain('Total');
});

/**
 * The prices are suppressed, and so is every trace of the currency they would
 * have been printed in — a lone `K` next to a quantity still tells the person
 * signing that this sheet is about money.
 */
it('prints no currency on a sheet that shows no money', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $body = $this->actingAs($user)->get("/delivery-notes/{$note->id}/preview")->getContent();

    expect($body)
        ->not->toContain('ZMW')
        ->not->toContain('Sub Total')
        ->not->toContain('VAT');
});

it('addresses the printed sheet to where the goods go', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    expect($this->actingAs($user)->get("/delivery-notes/{$note->id}/preview")->getContent())
        ->toContain('Deliver to:');
});

/* ---------------------------- Sign-off ---------------------------- */

it('records who signed for the goods', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/delivery-notes/{$note->id}", [
            'status' => 'delivered',
            'delivery_date' => '2026-07-05',
            'received_by' => 'J. Banda',
            'received_on' => '2026-07-05',
            'deliver_to' => 'Acme Warehouse',
            'delivery_address' => '12 Cairo Road, Lusaka',
        ])
        ->assertSessionHasNoErrors();

    $note = $note->fresh();

    expect($note->status)->toBe('delivered')
        ->and($note->received_by)->toBe('J. Banda')
        ->and($note->received_on->toDateString())->toBe('2026-07-05')
        ->and($note->deliver_to)->toBe('Acme Warehouse');
});

it('rejects a delivery status it does not use', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/delivery-notes/{$note->id}", ['status' => 'paid'])
        ->assertSessionHasErrors('status');
});

/* ---------------------------- Index ---------------------------- */

it('lists delivery notes for the active company', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");

    $this->actingAs($user)
        ->get('/delivery-notes')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('DeliveryNotes/Index')
            ->has('deliveryNotes', 1)
            ->where('deliveryNotes.0.number', 'DN-000001')
        );
});

/**
 * The page component is `DeliveryNotes/show` — lowercase, matching the sibling
 * document modules. With `ensure_pages_exist` enabled this also proves the file
 * is on disk under exactly that name.
 */
it('renders the delivery note screen', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = DeliveryNote::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/delivery-notes/{$note->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('DeliveryNotes/show')
            ->where('deliveryNote.number', 'DN-000001')
            ->has('deliveryNote.items', 2)
            ->where('statuses', ['draft', 'dispatched', 'delivered'])
        );
});

/**
 * The screens carry the same promise as the printed sheet: no money anywhere.
 * Reaching for the `Money` component or a price field on either page is the
 * single mistake that would break it, so it is asserted at the source.
 */
it('keeps money off the delivery note screens', function (string $file) {
    $source = file_get_contents(__DIR__.'/../../resources/js/pages/DeliveryNotes/'.$file);

    expect($source)
        ->not->toContain('components/money')
        ->not->toContain('<Money')
        ->not->toContain('TotalRow')
        ->not->toContain('unit_price')
        ->not->toContain('line_total')
        ->not->toContain('currency_code');
})->with(['Index.tsx', 'show.tsx']);

/* ---------------------------- Signature block ---------------------------- */

it('prints a receipt-of-goods signature block', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = App\Models\DeliveryNote::query()->latest('id')->first();

    expect($this->actingAs($user)->get("/delivery-notes/{$note->id}/preview")->getContent())
        ->toContain('Received by')
        ->toContain('Signature')
        ->toContain('Date');
});

it('keeps the signature block off every other document type', function () {
    [$user, $invoice] = invoiceWithLines();

    expect($this->actingAs($user)->get("/invoices/{$invoice->id}/preview")->getContent())
        ->not->toContain('Received by');
});

/**
 * `<input type="date">` accepts only `Y-m-d` and renders anything else blank,
 * so a serialised Carbon would silently empty the sign-off form's date fields.
 */
it('sends dates in the shape a date input accepts', function () {
    [$user, $invoice] = invoiceWithLines();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/delivery-note");
    $note = App\Models\DeliveryNote::query()->latest('id')->first();

    $this->actingAs($user)->put("/delivery-notes/{$note->id}", [
        'status' => 'delivered',
        'delivery_date' => '2026-07-05',
        'received_by' => 'J. Banda',
        'received_on' => '2026-07-06',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get("/delivery-notes/{$note->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('deliveryNote.delivery_date', '2026-07-05')
            ->where('deliveryNote.received_on', '2026-07-06')
            ->where('deliveryNote.issue_date', now()->toDateString())
        );
});
