<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * A quotation with two priced lines, a whole-document discount and tax, so the
 * copy has something to get wrong.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Quotation}
 */
function quotationToInvoice(string $status = 'sent'): array
{
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $quotation = Quotation::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'quotation_template_id' => $template->id,
        'number' => 'QUO-000001',
        'reference' => 'RFQ-88',
        'title' => 'Warehouse fit-out',
        'issue_date' => '2026-07-01',
        'valid_until' => '2026-07-31',
        'currency_code' => 'ZMW',
        'subtotal' => 4224.14,
        'discount_total' => 100,
        'quotation_discount' => 100,
        'tax_percent' => 16,
        'tax_total' => 675.86,
        'total' => 4900,
        'status' => $status,
        'notes' => 'Delivery within two weeks.',
        'terms' => 'Payment on delivery.',
    ]);

    QuotationItem::query()->create([
        'quotation_id' => $quotation->id,
        'description' => 'Steel shelving',
        'unit' => 'unit',
        'quantity' => 2,
        'unit_price' => 1500,
        'discount' => 0,
        'tax' => 0,
        'line_total' => 3000,
        'sort_order' => 0,
    ]);

    QuotationItem::query()->create([
        'quotation_id' => $quotation->id,
        'description' => 'Installation',
        'unit' => 'day',
        'quantity' => 4,
        'unit_price' => 500,
        'discount' => 0,
        'tax' => 0,
        'line_total' => 2000,
        'sort_order' => 1,
    ]);

    return [$user, $quotation];
}

/* ---------------------------- Creation ---------------------------- */

it('bills a quotation as a new invoice', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)
        ->post("/quotations/{$quotation->id}/invoice")
        ->assertRedirect();

    $invoice = Invoice::query()->latest('id')->first();

    expect($invoice->quotation_id)->toBe($quotation->id)
        ->and($invoice->client_id)->toBe($quotation->client_id)
        ->and($invoice->company_id)->toBe($quotation->company_id)
        ->and($invoice->currency_code)->toBe('ZMW')
        ->and($invoice->title)->toBe('Warehouse fit-out')
        ->and($invoice->notes)->toBe('Delivery within two weeks.')
        ->and($invoice->terms)->toBe('Payment on delivery.')
        ->and($invoice->status)->toBe('pending');
});

it('numbers the invoice in the invoice series, not the quotation one', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    expect(Invoice::query()->latest('id')->first()->number)->toBe('INV-000001');
});

/**
 * The quotation number goes onto `reference` as well as the foreign key, so it
 * prints on the sheet the client receives.
 */
it('references the quotation it came from', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    expect(Invoice::query()->latest('id')->first()->reference)->toBe('QUO-000001');
});

it('copies every line onto the invoice, in order', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    $items = Invoice::query()->latest('id')->first()->items;

    expect($items)->toHaveCount(2)
        ->and($items[0]->description)->toBe('Steel shelving')
        ->and((float) $items[0]->quantity)->toBe(2.0)
        ->and((float) $items[0]->unit_price)->toBe(1500.0)
        ->and($items[0]->unit)->toBe('unit')
        ->and($items[1]->description)->toBe('Installation')
        ->and((float) $items[1]->line_total)->toBe(2000.0);
});

it('bills exactly what was quoted', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    $invoice = Invoice::query()->latest('id')->first();

    expect((float) $invoice->total)->toBe((float) $quotation->total)
        ->and((float) $invoice->subtotal)->toBe((float) $quotation->subtotal)
        ->and((float) $invoice->tax_total)->toBe((float) $quotation->tax_total)
        ->and((float) $invoice->tax_percent)->toBe(16.0)
        ->and((float) $invoice->invoice_discount)->toBe(100.0)
        ->and((float) $invoice->discount_total)->toBe(100.0);
});

/**
 * The quotation's own dates say when the price was offered and when it lapses.
 * Neither is when the money is due, so the invoice is dated from today.
 */
it('dates the invoice today rather than from the quotation', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    $invoice = Invoice::query()->latest('id')->first();

    expect($invoice->issue_date->toDateString())->toBe(now()->toDateString())
        ->and($invoice->due_date->toDateString())->toBe(now()->toDateString());
});

/* ---------------------------- Status ---------------------------- */

it('accepts a quotation that gets billed', function (string $status) {
    [$user, $quotation] = quotationToInvoice($status);

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    expect($quotation->fresh()->status)->toBe('accepted');
})->with(['draft', 'sent']);

/**
 * A client can come back after the price lapsed and still be billed, but saying
 * the quotation was accepted would rewrite what actually happened.
 */
it('leaves an expired quotation expired', function () {
    [$user, $quotation] = quotationToInvoice('expired');

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    expect($quotation->fresh()->status)->toBe('expired')
        ->and(Invoice::query()->count())->toBe(1);
});

/* ---------------------------- Refusals ---------------------------- */

it('returns the existing invoice instead of billing twice', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");
    $first = Invoice::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/quotations/{$quotation->id}/invoice")
        ->assertRedirect(route('invoices.show', $first))
        ->assertSessionHas('info');

    expect(Invoice::query()->count())->toBe(1);
});

it('refuses a quotation belonging to another company', function () {
    [$user] = quotationToInvoice();
    [, $foreignQuotation] = quotationToInvoice();

    $this->actingAs($user)
        ->post("/quotations/{$foreignQuotation->id}/invoice")
        ->assertForbidden();

    expect(Invoice::query()->count())->toBe(0);
});

it('refuses to exceed the plan invoice allowance', function () {
    [$user, $quotation] = quotationToInvoice();
    $user->activePlan()->update(['max_invoices' => 0]);

    $this->actingAs($user)
        ->post("/quotations/{$quotation->id}/invoice")
        ->assertSessionHas('limit_notice');

    expect(Invoice::query()->count())->toBe(0)
        ->and($quotation->fresh()->status)->toBe('sent');
});

/* ---------------------------- Pages ---------------------------- */

it('shows the invoice on the quotation once it is billed', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)
        ->get("/quotations/{$quotation->id}")
        ->assertInertia(fn ($page) => $page->where('invoice', null));

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");

    $this->actingAs($user)
        ->get("/quotations/{$quotation->id}")
        ->assertInertia(fn ($page) => $page->where('invoice.number', 'INV-000001'));
});

it('shows the quotation on the invoice it was billed as', function () {
    [$user, $quotation] = quotationToInvoice();

    $this->actingAs($user)->post("/quotations/{$quotation->id}/invoice");
    $invoice = Invoice::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/invoices/{$invoice->id}")
        ->assertInertia(fn ($page) => $page->where('invoice.quotation.number', 'QUO-000001'));
});
