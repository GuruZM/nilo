<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;

/**
 * Quotations, credit notes and purchase orders over the API.
 *
 * Invoices get their own file because they carry payments and delivery notes;
 * these three share one, since what differs between them is mostly which
 * counterparty they point at and which statuses they accept.
 */
beforeEach(function () {
    Currency::query()->updateOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true,
    ]);
});

/* ----------------------------------------------------------- Quotations -- */

it('creates a quotation with the same tax-inclusive totals as an invoice', function () {
    [$user, $client] = apiContext();
    $template = quotationTemplateFor($user);

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.store'), apiQuotationPayload($client, $template, ['tax_percent' => 16]));

    $response->assertCreated();

    expect((float) $response->json('data.total'))->toBe(5000.00)
        ->and((float) $response->json('data.subtotal'))->toBe(4310.34)
        ->and((float) $response->json('data.tax_total'))->toBe(689.66);
});

it('numbers quotations per company', function () {
    [$user, $client] = apiContext();
    $template = quotationTemplateFor($user);

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.store'), apiQuotationPayload($client, $template));

    expect($response->json('data.number'))->toBe('QUO-000001');
});

it('refuses a quotation on an invoice template', function () {
    [$user, $client, $invoiceTemplate] = apiContext();

    /** A quotation template must exist, or the prerequisites gate answers first. */
    quotationTemplateFor($user);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.store'), apiQuotationPayload($client, $invoiceTemplate))
        ->assertStatus(422)
        ->assertJsonValidationErrors('quotation_template_id');
});

it('names the missing quotation template as a blocker before validating anything', function () {
    [$user, $client, $invoiceTemplate] = apiContext();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.store'), apiQuotationPayload($client, $invoiceTemplate));

    $response->assertStatus(422)->assertJsonPath('error', 'prerequisites_unmet');

    expect(collect($response->json('blockers'))->pluck('key')->all())->toContain('template');
});

it('moves a quotation through its workflow', function () {
    [$user, $client] = apiContext();

    $quotation = Quotation::factory()->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id, 'status' => 'draft',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.status', $quotation), ['status' => 'accepted'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'accepted');
});

it('rejects a status the quotation workflow does not use', function () {
    [$user, $client] = apiContext();

    $quotation = Quotation::factory()->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.status', $quotation), ['status' => 'haggling'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('lists and scopes quotations to the active company', function () {
    [$user, $client] = apiContext();

    Quotation::factory()->count(2)->create(['company_id' => $user->current_company_id, 'client_id' => $client->id]);

    $stranger = Company::factory()->create();
    Quotation::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => Client::factory()->create(['company_id' => $stranger->id])->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.quotations.index'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('refuses to create past the quotation plan limit', function () {
    [$user, $client] = apiContext();
    $template = quotationTemplateFor($user);

    $user->activePlan()->update(['max_quotations' => 0]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.store'), apiQuotationPayload($client, $template))
        ->assertStatus(402)
        ->assertJsonPath('error', 'plan_limit_reached');
});

it('serves a quotation as a pdf', function () {
    [$user, $client] = apiContext();
    $template = quotationTemplateFor($user);

    $created = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.quotations.store'), apiQuotationPayload($client, $template));

    $response = $this->withHeaders(apiHeaders($user))
        ->get(route('api.v1.quotations.pdf', $created->json('data.id')));

    $response->assertSuccessful();

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

/* --------------------------------------------------------- Credit notes -- */

it('raises a credit note against an invoice in the invoice\'s currency', function () {
    [$user, $invoice] = apiCreditableInvoice();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.store'), apiCreditNotePayload($invoice, 1000));

    $response->assertCreated()
        ->assertJsonPath('data.currency_code', 'ZMW')
        ->assertJsonPath('data.status', 'issued');

    expect($response->json('data.number'))->toStartWith('CRN-')
        ->and((float) $response->json('data.total'))->toBe(1000.00);
});

/**
 * An applied credit cannot exceed what the invoice is still owed, or the ledger
 * would show a negative balance.
 */
it('refuses a credit larger than the invoice still owes', function () {
    [$user, $invoice] = apiCreditableInvoice();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.store'), apiCreditNotePayload($invoice, 9000));

    $response->assertStatus(422)->assertJsonValidationErrors('items');

    expect(CreditNote::count())->toBe(0);
});

it('lets an oversized credit through as a draft, because a draft moves nothing', function () {
    [$user, $invoice] = apiCreditableInvoice();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.store'), apiCreditNotePayload($invoice, 9000, 'draft'))
        ->assertCreated();
});

it('settles the invoice as the credit is applied', function () {
    [$user, $invoice] = apiCreditableInvoice();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.store'), apiCreditNotePayload($invoice, 5000))
        ->assertCreated();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('refuses an invoice from another company', function () {
    [$user] = apiContext();

    $stranger = Company::factory()->create();
    $invoice = Invoice::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => Client::factory()->create(['company_id' => $stranger->id])->id,
        'total' => 5000,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.store'), apiCreditNotePayload($invoice, 100))
        ->assertStatus(422)
        ->assertJsonValidationErrors('invoice_id');
});

it('rechecks the headroom when a draft credit is issued', function () {
    [$user, $invoice] = apiCreditableInvoice();

    $draft = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.store'), apiCreditNotePayload($invoice, 9000, 'draft'));

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.credit-notes.status', $draft->json('data.id')), ['status' => 'issued'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

/* ------------------------------------------------------ Purchase orders -- */

it('creates a purchase order against a supplier', function () {
    [$user] = apiContext();
    $supplier = Supplier::factory()->create(['company_id' => $user->current_company_id]);

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.purchase-orders.store'), apiPurchaseOrderPayload($supplier));

    $response->assertCreated()->assertJsonPath('data.supplier_id', $supplier->id);

    expect($response->json('data.number'))->toBe('PO-000001')
        ->and((float) $response->json('data.total'))->toBe(5000.00);
});

it('refuses a supplier from another company', function () {
    [$user] = apiContext();

    $stranger = Supplier::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.purchase-orders.store'), apiPurchaseOrderPayload($stranger))
        ->assertStatus(422)
        ->assertJsonValidationErrors('supplier_id');
});

it('refuses goods expected before the order was placed', function () {
    [$user] = apiContext();
    $supplier = Supplier::factory()->create(['company_id' => $user->current_company_id]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.purchase-orders.store'), [
            ...apiPurchaseOrderPayload($supplier),
            'issue_date' => '2026-07-10',
            'expected_date' => '2026-07-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('expected_date');
});

it('moves a purchase order through its workflow', function () {
    [$user] = apiContext();

    $order = PurchaseOrder::factory()->create([
        'company_id' => $user->current_company_id,
        'supplier_id' => Supplier::factory()->create(['company_id' => $user->current_company_id])->id,
        'status' => 'draft',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.purchase-orders.status', $order), ['status' => 'received'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'received');
});

it('refuses to create past the purchase order plan limit', function () {
    [$user] = apiContext();
    $supplier = Supplier::factory()->create(['company_id' => $user->current_company_id]);

    $user->activePlan()->update(['max_purchase_orders' => 0]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.purchase-orders.store'), apiPurchaseOrderPayload($supplier))
        ->assertStatus(402)
        ->assertJsonPath('error', 'plan_limit_reached');
});

it('serves a purchase order as a pdf', function () {
    [$user] = apiContext();
    $supplier = Supplier::factory()->create(['company_id' => $user->current_company_id]);

    $created = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.purchase-orders.store'), apiPurchaseOrderPayload($supplier));

    $response = $this->withHeaders(apiHeaders($user))
        ->get(route('api.v1.purchase-orders.pdf', $created->json('data.id')));

    $response->assertSuccessful();

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

/* --------------------------------------------------------------- Helpers -- */

function quotationTemplateFor(App\Models\User $user): InvoiceTemplate
{
    return InvoiceTemplate::create([
        'company_id' => $user->current_company_id,
        'name' => 'Default quotation',
        'type' => 'quotation',
        'is_default' => true,
        'settings' => [],
    ]);
}

/**
 * @return array{0: \App\Models\User, 1: \App\Models\Invoice}
 */
function apiCreditableInvoice(float $total = 5000): array
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiQuotationPayload(Client $client, InvoiceTemplate $template, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'quotation_template_id' => $template->id,
        'issue_date' => '2026-07-01',
        'valid_until' => '2026-07-31',
        'currency_code' => 'ZMW',
        'status' => 'draft',
        'quotation_discount' => 0,
        'tax_percent' => 0,
        'items' => [
            ['description' => 'Consulting', 'unit' => 'hour', 'quantity' => 10, 'unit_price' => 500, 'discount' => 0],
        ],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function apiCreditNotePayload(Invoice $invoice, float $amount, string $status = 'issued'): array
{
    return [
        'invoice_id' => $invoice->id,
        'issue_date' => '2026-07-15',
        'status' => $status,
        'reason' => 'Goods returned',
        'credit_note_discount' => 0,
        'tax_percent' => 0,
        'items' => [
            ['description' => 'Returned hours', 'quantity' => 1, 'unit_price' => $amount, 'discount' => 0],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function apiPurchaseOrderPayload(Supplier $supplier): array
{
    return [
        'supplier_id' => $supplier->id,
        'issue_date' => '2026-07-01',
        'expected_date' => '2026-07-15',
        'currency_code' => 'ZMW',
        'status' => 'draft',
        'purchase_order_discount' => 0,
        'tax_percent' => 0,
        'items' => [
            ['description' => 'Steel bolts', 'unit' => 'box', 'quantity' => 10, 'unit_price' => 500, 'discount' => 0],
        ],
    ];
}
