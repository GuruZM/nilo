<?php

use App\Mail\InvoiceToClient;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Currency::query()->updateOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true,
    ]);
});

/* --------------------------------------------------------------- Listing -- */

it('lists invoices for the active company, newest first and paginated', function () {
    [$user, $client] = apiContext();

    Invoice::factory()->count(3)->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.index', ['per_page' => 2]));

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonStructure(['data' => [['id', 'number', 'status', 'total', 'client_name']], 'meta', 'links']);
});

it('never lists another company\'s invoices', function () {
    [$user] = apiContext();

    $stranger = Company::factory()->create();
    Invoice::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => Client::factory()->create(['company_id' => $stranger->id])->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.index'))
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('filters the list by status and by client', function () {
    [$user, $client] = apiContext();

    Invoice::factory()->create(['company_id' => $user->current_company_id, 'client_id' => $client->id, 'status' => 'paid']);
    Invoice::factory()->count(2)->create(['company_id' => $user->current_company_id, 'client_id' => $client->id, 'status' => 'pending']);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.index', ['status' => 'paid']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.index', ['client_id' => $client->id]))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

/* -------------------------------------------------------------- Creating -- */

/**
 * The money contract, and the reason DocumentTotals was extracted: a phone and
 * a browser sending the same lines must produce the same invoice. Prices are
 * tax-inclusive, so 5000 at 16% is 4310.34 net plus 689.66 tax.
 */
it('creates an invoice with the same tax-inclusive totals the web app produces', function () {
    [$user, $client, $template] = apiContext();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template, ['tax_percent' => 16]));

    $response->assertCreated();

    /** JSON renders a whole float as an integer, so compare as numbers. */
    expect((float) $response->json('data.total'))->toBe(5000.00)
        ->and((float) $response->json('data.subtotal'))->toBe(4310.34)
        ->and((float) $response->json('data.tax_total'))->toBe(689.66)
        ->and(round((float) $response->json('data.subtotal') + (float) $response->json('data.tax_total'), 2))
        ->toBe((float) $response->json('data.total'));
});

it('files the new invoice against the company in the header', function () {
    [$user, $client, $template] = apiContext();

    $second = Company::factory()->create(['currency_code' => 'ZMW']);
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    $secondClient = Client::factory()->create(['company_id' => $second->id]);
    $secondTemplate = InvoiceTemplate::create([
        'company_id' => $second->id, 'name' => 'Default', 'type' => 'invoice', 'is_default' => true, 'settings' => [],
    ]);

    $this->withHeaders(apiHeaders($user, $second->id))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($secondClient, $secondTemplate))
        ->assertCreated();

    expect(Invoice::latest('id')->value('company_id'))->toBe($second->id);
});

it('returns the created invoice with its lines and settlement figures', function () {
    [$user, $client, $template] = apiContext();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template));

    $response->assertCreated()
        ->assertJsonStructure(['data' => [
            'id', 'number', 'status', 'currency_code', 'subtotal', 'tax_total', 'total',
            'amount_paid', 'amount_credited', 'balance_due',
            'client' => ['id', 'name'],
            'items' => [['description', 'quantity', 'unit_price', 'line_total']],
        ]]);

    expect((float) $response->json('data.balance_due'))->toBe(5000.00)
        ->and((float) $response->json('data.amount_paid'))->toBe(0.00);
});

/**
 * Cross-tenant ids must never validate. Without the company scoping on the
 * exists rule, another tenant's client would be written onto this invoice.
 */
it('refuses a client belonging to another company', function () {
    [$user, , $template] = apiContext();

    $stranger = Client::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($stranger, $template))
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_id');
});

it('refuses a template belonging to the quotation type', function () {
    [$user, $client] = apiContext();

    $quotationTemplate = InvoiceTemplate::create([
        'company_id' => $user->current_company_id,
        'name' => 'Quotation default', 'type' => 'quotation', 'is_default' => true, 'settings' => [],
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $quotationTemplate))
        ->assertStatus(422)
        ->assertJsonValidationErrors('invoice_template_id');
});

it('refuses an invoice with no lines', function () {
    [$user, $client, $template] = apiContext();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), [
            ...apiInvoicePayload($client, $template),
            'items' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

it('refuses a due date before the issue date', function () {
    [$user, $client, $template] = apiContext();

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), [
            ...apiInvoicePayload($client, $template),
            'issue_date' => '2026-07-10',
            'due_date' => '2026-07-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('due_date');
});

/* --------------------------------------------------------------- Refusals -- */

/**
 * The web app flashes this refusal as a dialog, which a stateless client would
 * discard. The API has to say it in a status, and carry the same wording.
 */
it('refuses to create past the plan limit with a payment required status', function () {
    [$user, $client, $template] = apiContext();

    $user->activePlan()->update(['max_invoices' => 1]);

    Invoice::factory()->create(['company_id' => $user->current_company_id, 'client_id' => $client->id]);

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template));

    $response->assertStatus(402)
        ->assertJsonPath('error', 'plan_limit_reached')
        ->assertJsonStructure(['limit_notice' => ['title', 'message', 'action_label', 'action_href']]);
});

/**
 * The limit counts invoices in the company the request names, not the one the
 * browser happens to have open — which is what the middleware's in-memory
 * company assignment exists to make true.
 */
it('counts the plan limit against the company in the header', function () {
    [$user, $client, $template] = apiContext();

    $user->activePlan()->update(['max_invoices' => 1]);

    /** The stored company is already at its limit. */
    Invoice::factory()->create(['company_id' => $user->current_company_id, 'client_id' => $client->id]);

    $second = Company::factory()->create(['currency_code' => 'ZMW']);
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    $secondClient = Client::factory()->create(['company_id' => $second->id]);
    $secondTemplate = InvoiceTemplate::create([
        'company_id' => $second->id, 'name' => 'Default', 'type' => 'invoice', 'is_default' => true, 'settings' => [],
    ]);

    /** The second company has used none of it, so this must be allowed. */
    $this->withHeaders(apiHeaders($user, $second->id))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($secondClient, $secondTemplate))
        ->assertCreated();
});

it('names the unmet prerequisites rather than failing opaquely', function () {
    [$user, $client, $template] = apiContext();

    InvoiceTemplate::query()->where('company_id', $user->current_company_id)->delete();

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template));

    $response->assertStatus(422)->assertJsonPath('error', 'prerequisites_unmet');

    expect(collect($response->json('blockers'))->pluck('key')->all())->toContain('template');
});

it('refuses a user with no active plan before it reaches the invoice at all', function () {
    [$user, $client, $template] = apiContext();

    $user->subscriptions()->update(['status' => 'cancelled']);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template))
        ->assertStatus(402)
        ->assertJsonPath('error', 'subscription_required');
});

/* ------------------------------------------------------------ Show & PDF -- */

it('refuses to show an invoice from another company', function () {
    [$user] = apiContext();

    $stranger = Company::factory()->create();
    $invoice = Invoice::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => Client::factory()->create(['company_id' => $stranger->id])->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.show', $invoice))
        ->assertForbidden();
});

it('updates the invoice status', function () {
    [$user, $client] = apiContext();

    $invoice = Invoice::factory()->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id, 'status' => 'pending',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.status', $invoice), ['status' => 'paid'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'paid');
});

it('rejects a status the invoice workflow does not offer', function () {
    [$user, $client] = apiContext();

    $invoice = Invoice::factory()->create(['company_id' => $user->current_company_id, 'client_id' => $client->id]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.status', $invoice), ['status' => 'obliterated'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('serves the invoice as a pdf the phone can open', function () {
    [$user, $client, $template] = apiContext();

    $created = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template));

    $response = $this->withHeaders(apiHeaders($user))
        ->get(route('api.v1.invoices.pdf', $created->json('data.id')));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('refuses to serve a pdf for another company\'s invoice', function () {
    [$user] = apiContext();

    $stranger = Company::factory()->create();
    $invoice = Invoice::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => Client::factory()->create(['company_id' => $stranger->id])->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.invoices.pdf', $invoice))
        ->assertForbidden();
});

/* -------------------------------------------------- Delivery note raising -- */

it('raises a delivery note from an invoice and copies its lines without prices', function () {
    [$user, $client, $template] = apiContext();

    $created = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template));

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.delivery-note.store', $created->json('data.id')));

    $response->assertCreated()->assertJsonPath('data.status', 'draft');

    expect($response->json('data.items.0'))->not->toHaveKey('unit_price')
        ->and($response->json('data'))->not->toHaveKey('total')
        ->and(Invoice::find($created->json('data.id'))->has_delivery_note)->toBeTrue();
});

it('signs off a delivery note', function () {
    [$user, $client, $template] = apiContext();

    $created = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.store'), apiInvoicePayload($client, $template));

    $note = $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.delivery-note.store', $created->json('data.id')));

    $this->withHeaders(apiHeaders($user))
        ->patchJson(route('api.v1.delivery-notes.update', $note->json('data.id')), [
            'status' => 'delivered',
            'received_by' => 'Mwansa Banda',
            'received_on' => '2026-07-20',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.received_by', 'Mwansa Banda')
        ->assertJsonPath('data.received_on', '2026-07-20');
});

it('rejects a delivery status it does not use', function () {
    [$user, $client] = apiContext();

    $note = DeliveryNote::factory()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->patchJson(route('api.v1.delivery-notes.update', $note), ['status' => 'teleported'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

/* ------------------------------------------------------------- Sending -- */

it('emails an invoice to its client and marks it sent', function () {
    Mail::fake();

    [$user, $client] = apiContext('accounts@urbanmasai.co.zm');

    $invoice = Invoice::factory()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'status' => 'pending',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.send', $invoice))
        ->assertSuccessful()
        /** An emailed invoice is no longer merely pending. */
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('message', 'Invoice queued to accounts@urbanmasai.co.zm.');

    Mail::assertQueued(
        InvoiceToClient::class,
        fn (InvoiceToClient $mail) => $mail->hasTo('accounts@urbanmasai.co.zm')
            && $mail->invoice->is($invoice),
    );
});

it('refuses to send an invoice whose client has no email address', function () {
    Mail::fake();

    // apiContext() leaves the client's email null unless one is named.
    [$user, $client] = apiContext();

    $invoice = Invoice::factory()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'status' => 'pending',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.send', $invoice))
        ->assertStatus(422)
        ->assertJsonPath('error', 'not_deliverable');

    Mail::assertNothingQueued();

    /** Nothing was sent, so nothing may claim it was. */
    expect($invoice->fresh()->status)->toBe('pending');
});

it('leaves a settled invoice settled when it is emailed again', function () {
    Mail::fake();

    [$user, $client] = apiContext('accounts@urbanmasai.co.zm');

    $invoice = Invoice::factory()->create([
        'company_id' => $user->current_company_id,
        'client_id' => $client->id,
        'status' => 'paid',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.send', $invoice))
        ->assertSuccessful()
        /** Only `pending` is promoted; sending a receipt-worthy invoice is fine. */
        ->assertJsonPath('data.status', 'paid');

    Mail::assertQueued(InvoiceToClient::class);
});

it('never sends another company\'s invoice', function () {
    Mail::fake();

    [$user] = apiContext('accounts@urbanmasai.co.zm');

    $stranger = Company::factory()->create();
    $strangerClient = Client::factory()->create([
        'company_id' => $stranger->id,
        'email' => 'someone@else.test',
    ]);
    $invoice = Invoice::factory()->create([
        'company_id' => $stranger->id,
        'client_id' => $strangerClient->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.invoices.send', $invoice))
        ->assertForbidden();

    Mail::assertNothingQueued();
});

/* --------------------------------------------------------------- Helpers -- */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiInvoicePayload(Client $client, InvoiceTemplate $template, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'invoice_template_id' => $template->id,
        'issue_date' => '2026-07-01',
        'due_date' => '2026-07-31',
        'currency_code' => 'ZMW',
        'status' => 'pending',
        'has_delivery_note' => false,
        'is_recurring' => false,
        'invoice_discount' => 0,
        'tax_percent' => 0,
        'items' => [
            ['description' => 'Consulting', 'unit' => 'hour', 'quantity' => 10, 'unit_price' => 500, 'discount' => 0],
        ],
        ...$overrides,
    ];
}
