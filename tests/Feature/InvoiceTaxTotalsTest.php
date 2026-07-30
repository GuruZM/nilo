<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

/**
 * Posts an invoice with the given money shape and returns what was stored.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function createInvoiceWith(array $items, float $invoiceDiscount, float $taxPercent): Invoice
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $payload = invoicePayload($client, $template, false);
    $payload['items'] = $items;
    $payload['invoice_discount'] = $invoiceDiscount;
    $payload['tax_percent'] = $taxPercent;

    test()->actingAs($user)
        ->post('/invoices', $payload)
        ->assertSessionHasNoErrors();

    return Invoice::query()->latest('id')->first();
}

/** A single line charging exactly 5,000 — the worked example. */
$fiveThousand = [
    ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 0],
];

it('carves tax out of the entered price rather than adding it on top', function () use ($fiveThousand) {
    $invoice = createInvoiceWith($fiveThousand, 0, 16);

    // 5000 is what the client pays; 5000 ÷ 1.16 = 4310.34 net, 689.66 tax.
    expect((float) $invoice->total)->toBe(5000.0)
        ->and((float) $invoice->subtotal)->toBe(4310.34)
        ->and((float) $invoice->tax_total)->toBe(689.66)
        ->and((float) $invoice->tax_percent)->toBe(16.0);
});

/** Stored columns are decimal(14,2); the sum is rounded so IEEE754 noise in the
 *  assertion itself cannot masquerade as a reconciliation failure. */
function reconciles(Invoice $invoice): bool
{
    return round((float) $invoice->subtotal + (float) $invoice->tax_total, 2)
        === round((float) $invoice->total, 2);
}

it('always reconciles subtotal plus tax back to the total', function () use ($fiveThousand) {
    expect(reconciles(createInvoiceWith($fiveThousand, 0, 16)))->toBeTrue();
});

it('takes discounts off the gross before carving out the tax', function () {
    $invoice = createInvoiceWith(
        [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 100]],
        invoiceDiscount: 400,
        taxPercent: 16,
    );

    // 5000 − 100 − 400 = 4500 gross; 4500 ÷ 1.16 = 3879.31 net, 620.69 tax.
    expect((float) $invoice->total)->toBe(4500.0)
        ->and((float) $invoice->discount_total)->toBe(500.0)
        ->and((float) $invoice->invoice_discount)->toBe(400.0)
        ->and((float) $invoice->subtotal)->toBe(3879.31)
        ->and((float) $invoice->tax_total)->toBe(620.69);
});

it('leaves the subtotal equal to the total at a zero rate', function () use ($fiveThousand) {
    $invoice = createInvoiceWith($fiveThousand, 0, 0);

    expect((float) $invoice->total)->toBe(5000.0)
        ->and((float) $invoice->subtotal)->toBe(5000.0)
        ->and((float) $invoice->tax_total)->toBe(0.0);
});

it('never goes negative when discounts exceed the charge', function () use ($fiveThousand) {
    $invoice = createInvoiceWith($fiveThousand, 9000, 16);

    expect((float) $invoice->total)->toBe(0.0)
        ->and((float) $invoice->subtotal)->toBe(0.0)
        ->and((float) $invoice->tax_total)->toBe(0.0);
});

it('keeps the reconciliation exact on amounts that do not divide cleanly', function () {
    $invoice = createInvoiceWith(
        [['description' => 'Odd', 'quantity' => 1, 'unit_price' => 333.33, 'discount' => 0]],
        invoiceDiscount: 0,
        taxPercent: 16,
    );

    // 333.33 ÷ 1.16 = 287.3534…, which rounds to 287.35; tax is the remainder.
    expect((float) $invoice->total)->toBe(333.33)
        ->and((float) $invoice->subtotal)->toBe(287.35)
        ->and((float) $invoice->tax_total)->toBe(45.98)
        ->and(reconciles($invoice))->toBeTrue();
});

it('stores the gross charge on the line item and no per-item tax', function () use ($fiveThousand) {
    $invoice = createInvoiceWith($fiveThousand, 0, 16);

    $item = InvoiceItem::query()->where('invoice_id', $invoice->id)->first();

    expect((float) $item->tax)->toBe(0.0)
        ->and((float) $item->line_total)->toBe(5000.0);
});

it('rejects a tax rate outside 0 to 100', function () use ($fiveThousand) {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $payload = invoicePayload($client, $template, false);
    $payload['items'] = $fiveThousand;
    $payload['tax_percent'] = 140;

    $this->actingAs($user)
        ->post('/invoices', $payload)
        ->assertSessionHasErrors('tax_percent');
});

it('previews the same total that creating the invoice produces', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $payload = invoicePayload($client, $template, false);
    $payload['items'] = [
        ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 100],
    ];
    $payload['invoice_discount'] = 400;
    $payload['tax_percent'] = 16;

    $preview = $this->actingAs($user)->post('/invoices/preview', $payload);
    $preview->assertSuccessful();

    $this->actingAs($user)->post('/invoices', $payload);
    $invoice = Invoice::query()->latest('id')->first();

    /** The preview renders the same 4,500.00 the stored invoice carries. */
    expect($preview->getContent())->toContain('4,500.00')
        ->and((float) $invoice->total)->toBe(4500.0);
});
