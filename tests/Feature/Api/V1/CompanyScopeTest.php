<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

/**
 * How the API decides which company a request is acting for.
 *
 * This is the one place the API deliberately departs from the web app. The web
 * app keeps the active company on the user row, which is fine for a single
 * browser session but cannot serve a phone and an open tab at once. Everything
 * below pins that difference down.
 */

/* ------------------------------------------------------- Header wins -- */

it('acts for the company named in the header', function () {
    [$user] = apiContext();

    $second = Company::factory()->create(['name' => 'Second Ltd']);
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    $mine = Client::factory()->create(['company_id' => $second->id, 'name' => 'Only in second']);
    Client::factory()->create(['company_id' => $user->current_company_id, 'name' => 'Only in first']);

    $response = $this->withHeaders(apiHeaders($user, $second->id))
        ->getJson(route('api.v1.clients.index'));

    $response->assertSuccessful();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$mine->id]);
});

/**
 * The whole point of the header: a phone working in one company must not drag
 * an open browser tab into it.
 */
it('never writes the active company back to the user', function () {
    [$user] = apiContext();
    $first = $user->current_company_id;

    $second = Company::factory()->create(['name' => 'Second Ltd']);
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    $this->withHeaders(apiHeaders($user, $second->id))
        ->getJson(route('api.v1.clients.index'))
        ->assertSuccessful();

    expect($user->fresh()->current_company_id)->toBe($first);
});

it('falls back to the stored company when no header is sent', function () {
    [$user, $client] = apiContext();

    $headers = apiHeaders($user);
    unset($headers['X-Company-Id']);

    $response = $this->withHeaders($headers)->getJson(route('api.v1.clients.index'));

    $response->assertSuccessful();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$client->id]);
});

/* ------------------------------------------------------------ Refusals -- */

it('refuses a company the user does not belong to', function () {
    [$user] = apiContext();

    $stranger = Company::factory()->create();

    $this->withHeaders(apiHeaders($user, $stranger->id))
        ->getJson(route('api.v1.clients.index'))
        ->assertForbidden();
});

it('refuses a request from a user with no company at all', function () {
    $user = User::factory()->withSubscription()->create();

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.clients.index'))
        ->assertStatus(409);
});

/**
 * Route model binding resolves by id alone, so a row from another company
 * binds perfectly well and has to be refused explicitly.
 */
it('refuses to read a record belonging to another company', function () {
    [$user] = apiContext();

    $stranger = Client::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.clients.show', $stranger))
        ->assertForbidden();
});

it('refuses to delete a record belonging to another company', function () {
    [$user] = apiContext();

    $stranger = Client::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->withHeaders(apiHeaders($user))
        ->deleteJson(route('api.v1.clients.destroy', $stranger))
        ->assertForbidden();

    expect(Client::whereKey($stranger->id)->exists())->toBeTrue();
});

/* --------------------------------------------------------------- Usage -- */

/**
 * `/me` sits outside the company middleware on purpose — the app has to read
 * it before it knows which company to name — so its usage figures describe the
 * stored company, which is the starting point rather than the working state.
 * That the *header's* company drives the figures once inside the gate is
 * proved where it matters, against the plan limit in InvoiceApiTest.
 */
it('reports plan usage for the stored company, ignoring the header', function () {
    [$user] = apiContext();

    $second = Company::factory()->create(['name' => 'Second Ltd']);
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    Invoice::factory()->count(3)->create(['company_id' => $user->current_company_id]);
    Invoice::factory()->create(['company_id' => $second->id]);

    $this->withHeaders(apiHeaders($user, $second->id))
        ->getJson(route('api.v1.me'))
        ->assertSuccessful()
        ->assertJsonPath('subscription.usage.invoices.used', 3);
});
