<?php

use App\Mail\QuotationToClient;
use App\Models\Currency;
use App\Models\Quotation;
use App\Models\QuotationItem;
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
 * Posts a quotation with the given money shape and returns what was stored.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function createQuotationWith(array $items, float $quotationDiscount, float $taxPercent): Quotation
{
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $payload = quotationPayload($client, $template, false);
    $payload['items'] = $items;
    $payload['quotation_discount'] = $quotationDiscount;
    $payload['tax_percent'] = $taxPercent;

    test()->actingAs($user)
        ->post('/quotations', $payload)
        ->assertSessionHasNoErrors();

    return Quotation::query()->latest('id')->first();
}

/** A single line quoting exactly 5,000 — the worked example. */
$fiveThousand = [
    ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 0],
];

/* ---------------------------- Totals ---------------------------- */

it('carves tax out of the quoted price rather than adding it on top', function () use ($fiveThousand) {
    $quotation = createQuotationWith($fiveThousand, 0, 16);

    // 5000 is what the client would pay; 5000 ÷ 1.16 = 4310.34 net, 689.66 tax.
    expect((float) $quotation->total)->toBe(5000.0)
        ->and((float) $quotation->subtotal)->toBe(4310.34)
        ->and((float) $quotation->tax_total)->toBe(689.66)
        ->and((float) $quotation->tax_percent)->toBe(16.0);
});

it('takes discounts off the gross before carving out the tax', function () {
    $quotation = createQuotationWith(
        [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 100]],
        quotationDiscount: 400,
        taxPercent: 16,
    );

    // 5000 − 100 − 400 = 4500 gross; 4500 ÷ 1.16 = 3879.31 net, 620.69 tax.
    expect((float) $quotation->total)->toBe(4500.0)
        ->and((float) $quotation->discount_total)->toBe(500.0)
        ->and((float) $quotation->quotation_discount)->toBe(400.0)
        ->and((float) $quotation->subtotal)->toBe(3879.31)
        ->and((float) $quotation->tax_total)->toBe(620.69);
});

it('always reconciles subtotal plus tax back to the total', function () {
    $quotation = createQuotationWith(
        [['description' => 'Odd', 'quantity' => 1, 'unit_price' => 333.33, 'discount' => 0]],
        quotationDiscount: 0,
        taxPercent: 16,
    );

    expect((float) $quotation->total)->toBe(333.33)
        ->and((float) $quotation->subtotal)->toBe(287.35)
        ->and((float) $quotation->tax_total)->toBe(45.98)
        ->and(round((float) $quotation->subtotal + (float) $quotation->tax_total, 2))
        ->toBe(round((float) $quotation->total, 2));
});

it('never goes negative when discounts exceed the quote', function () use ($fiveThousand) {
    $quotation = createQuotationWith($fiveThousand, 9000, 16);

    expect((float) $quotation->total)->toBe(0.0)
        ->and((float) $quotation->subtotal)->toBe(0.0)
        ->and((float) $quotation->tax_total)->toBe(0.0);
});

it('stores the gross charge on the line item and no per-item tax', function () use ($fiveThousand) {
    $quotation = createQuotationWith($fiveThousand, 0, 16);

    $item = QuotationItem::query()->where('quotation_id', $quotation->id)->first();

    expect((float) $item->tax)->toBe(0.0)
        ->and((float) $item->line_total)->toBe(5000.0);
});

it('rejects a tax rate outside 0 to 100', function () use ($fiveThousand) {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $payload = quotationPayload($client, $template, false);
    $payload['items'] = $fiveThousand;
    $payload['tax_percent'] = 140;

    $this->actingAs($user)
        ->post('/quotations', $payload)
        ->assertSessionHasErrors('tax_percent');
});

/* ---------------------------- Workflow ---------------------------- */

it('redirects to the created quotation', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, false))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('quotations.show', Quotation::query()->latest('id')->first()));
});

it('emails the quotation to the client when asked to', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, true))
        ->assertSessionHasNoErrors();

    Mail::assertQueued(
        QuotationToClient::class,
        fn (QuotationToClient $mail) => $mail->hasTo('client@example.com')
    );
});

it('promotes an emailed quotation from draft to sent', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, true));

    expect(Quotation::query()->latest('id')->first()->status)->toBe('sent');
});

it('does not email the client when the toggle is off', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, false))
        ->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
    expect(Quotation::query()->latest('id')->first()->status)->toBe('draft');
});

it('still creates the quotation when the client has no email address', function () {
    [$user, $client, $template] = quotationCreationContext(null);

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, true))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    Mail::assertNothingQueued();

    expect(Quotation::query()->latest('id')->first()->status)->toBe('draft');
});

it('refuses a template belonging to the invoice type', function () {
    [$user, $client] = quotationCreationContext('client@example.com');

    $invoiceTemplate = App\Models\InvoiceTemplate::query()
        ->where('company_id', $user->current_company_id)
        ->where('type', 'invoice')
        ->first();

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $invoiceTemplate, false))
        ->assertSessionHasErrors('quotation_template_id');
});

/* ---------------------------- Preview ---------------------------- */

it('previews the same total that creating the quotation produces', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $payload = quotationPayload($client, $template, false);
    $payload['items'] = [
        ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 100],
    ];
    $payload['quotation_discount'] = 400;
    $payload['tax_percent'] = 16;

    $preview = $this->actingAs($user)->post('/quotations/preview', $payload);
    $preview->assertSuccessful();

    $this->actingAs($user)->post('/quotations', $payload);
    $quotation = Quotation::query()->latest('id')->first();

    /** The preview renders the same 4,500.00 the stored quotation carries. */
    expect($preview->getContent())->toContain('4,500.00')
        ->and((float) $quotation->total)->toBe(4500.0);
});

it('renders the preview as a quotation rather than an invoice', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $preview = $this->actingAs($user)
        ->post('/quotations/preview', quotationPayload($client, $template, false));

    $preview->assertSuccessful();

    expect($preview->getContent())
        ->toContain('>QUOTATION<')
        ->toContain('Valid until:')
        ->not->toContain('>INVOICE<');
});

it('renders a saved quotation through the shared document template', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, false));

    $quotation = Quotation::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/quotations/{$quotation->id}/preview")
        ->assertSuccessful()
        ->assertSee('QUOTATION', false);
});

/* ---------------------------- Show and status ---------------------------- */

it('shows the quotation with its discount split apart', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $payload = quotationPayload($client, $template, false);
    $payload['items'] = [
        ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 2500, 'discount' => 100],
    ];
    $payload['quotation_discount'] = 400;

    $this->actingAs($user)->post('/quotations', $payload);
    $quotation = Quotation::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/quotations/{$quotation->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/show')
            ->where('quotation.quotation_discount', 400)
            ->where('quotation.line_discount', 100)
        );
});

it('updates the quotation status', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, false));

    $quotation = Quotation::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/quotations/{$quotation->id}/status", ['status' => 'accepted'])
        ->assertSessionHasNoErrors();

    expect($quotation->fresh()->status)->toBe('accepted');
});

it('rejects a status the quotation workflow does not use', function () {
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/quotations', quotationPayload($client, $template, false));

    $quotation = Quotation::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/quotations/{$quotation->id}/status", ['status' => 'paid'])
        ->assertSessionHasErrors('status');
});

/* ---------------------------- Index ---------------------------- */

it('sends the prerequisites the index page gates on', function () {
    [$user] = quotationCreationContext('client@example.com');

    $this->actingAs($user)
        ->get('/quotations')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Quotations/Index')
            ->where('prerequisites.can_create', true)
        );
});
