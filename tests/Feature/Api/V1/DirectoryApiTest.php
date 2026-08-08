<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InvoiceTemplate;
use App\Models\Supplier;

/* -------------------------------------------------------------- Clients -- */

it('lists clients for the active company, paginated', function () {
    [$user] = apiContext();

    Client::factory()->count(30)->create(['company_id' => $user->current_company_id]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.clients.index', ['per_page' => 10]));

    $response->assertSuccessful()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 31);
});

/**
 * A phone must not be able to pull the whole table down by asking for it.
 */
it('caps the page size', function () {
    [$user] = apiContext();

    Client::factory()->count(150)->create(['company_id' => $user->current_company_id]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.clients.index', ['per_page' => 5000]))
        ->assertSuccessful()
        ->assertJsonPath('meta.per_page', 100);
});

it('never lists another company\'s clients', function () {
    [$user] = apiContext();

    Client::factory()->create(['company_id' => Company::factory()->create()->id, 'name' => 'Not mine']);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.clients.index'));

    expect(collect($response->json('data'))->pluck('name')->all())->not->toContain('Not mine');
});

it('creates a client in the active company', function () {
    [$user] = apiContext();

    $response = $this->withHeaders(apiHeaders($user))->postJson(route('api.v1.clients.store'), [
        'name' => 'Zambia Sugar Plc',
        'email' => 'accounts@zamsugar.example',
        'city' => 'Mazabuka',
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Zambia Sugar Plc');

    expect(Client::where('name', 'Zambia Sugar Plc')->value('company_id'))
        ->toBe($user->current_company_id);
});

/**
 * The web controller rejects duplicate names per company case-insensitively;
 * the API has to agree or the two clients would disagree about what is valid.
 */
it('refuses a duplicate client name in the same company, ignoring case', function () {
    [$user] = apiContext();

    Client::factory()->create(['company_id' => $user->current_company_id, 'name' => 'Zambia Sugar Plc']);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.clients.store'), ['name' => 'zambia sugar plc'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('allows the same client name in a different company', function () {
    [$user] = apiContext();

    $second = Company::factory()->create();
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    Client::factory()->create(['company_id' => $user->current_company_id, 'name' => 'Zambia Sugar Plc']);

    $this->withHeaders(apiHeaders($user, $second->id))
        ->postJson(route('api.v1.clients.store'), ['name' => 'Zambia Sugar Plc'])
        ->assertCreated();
});

it('lets a client keep its own name when updated', function () {
    [$user, $client] = apiContext();

    $this->withHeaders(apiHeaders($user))
        ->putJson(route('api.v1.clients.update', $client), [
            'name' => $client->name,
            'city' => 'Kitwe',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.city', 'Kitwe');
});

it('deletes a client', function () {
    [$user, $client] = apiContext();

    $this->withHeaders(apiHeaders($user))
        ->deleteJson(route('api.v1.clients.destroy', $client))
        ->assertSuccessful();

    expect(Client::whereKey($client->id)->exists())->toBeFalse();
});

/* ------------------------------------------------------------ Suppliers -- */

it('creates and lists suppliers scoped to the active company', function () {
    [$user] = apiContext();

    Supplier::factory()->create(['company_id' => Company::factory()->create()->id, 'name' => 'Not mine']);

    $this->withHeaders(apiHeaders($user))
        ->postJson(route('api.v1.suppliers.store'), ['name' => 'Bulk Paper Ltd'])
        ->assertCreated();

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.suppliers.index'));

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toBe(['Bulk Paper Ltd']);
});

it('refuses to update a supplier from another company', function () {
    [$user] = apiContext();

    $stranger = Supplier::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->withHeaders(apiHeaders($user))
        ->putJson(route('api.v1.suppliers.update', $stranger), ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($stranger->fresh()->name)->not->toBe('Hijacked');
});

/* -------------------------------------------- Templates & prerequisites -- */

it('lists templates for the requested document type only', function () {
    [$user, , $invoiceTemplate] = apiContext();

    InvoiceTemplate::create([
        'company_id' => $user->current_company_id,
        'name' => 'Quotation default',
        'type' => 'quotation',
        'is_default' => true,
        'settings' => [],
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.document-templates.index', ['type' => 'invoice']));

    $response->assertSuccessful();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$invoiceTemplate->id]);
});

it('refuses a template listing without a document type', function () {
    [$user] = apiContext();

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.document-templates.index'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

it('reports that invoices can be created once the setup is complete', function () {
    [$user] = apiContext();

    Currency::query()->updateOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.document-prerequisites', ['type' => 'invoice']))
        ->assertSuccessful()
        ->assertJsonPath('can_create', true)
        ->assertJsonPath('blockers', []);
});

/**
 * Blockers carry web hrefs, so the app is expected to branch on `key`. Pinning
 * the key here is what makes that contract safe to rely on.
 */
it('names the missing template as a blocker', function () {
    [$user] = apiContext();

    InvoiceTemplate::query()->where('company_id', $user->current_company_id)->delete();

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.document-prerequisites', ['type' => 'invoice']));

    $response->assertSuccessful()->assertJsonPath('can_create', false);

    expect(collect($response->json('blockers'))->pluck('key')->all())->toContain('template');
});
