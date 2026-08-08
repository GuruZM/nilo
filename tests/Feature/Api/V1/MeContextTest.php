<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\User;

it('returns everything the app needs to draw its shell', function () {
    [$user] = apiContext();

    Currency::query()->updateOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true,
    ]);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.me'));

    $response->assertSuccessful()->assertJsonStructure([
        'user' => ['id', 'name', 'email', 'email_verified', 'two_factor_enabled', 'linked_providers'],
        'roles',
        'companies' => ['all' => [['id', 'name', 'currency_code', 'is_owner']], 'current_id'],
        'currencies' => ['all', 'current', 'rates_as_of'],
        'subscription' => ['plan' => ['id', 'name', 'slug'], 'status', 'usage' => [
            'companies', 'invoices', 'quotations', 'purchase_orders',
            'invoice_templates', 'quotation_templates',
        ]],
    ]);

    expect($response->json('companies.current_id'))->toBe($user->current_company_id);
});

it('never exposes the password hash', function () {
    [$user] = apiContext();

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.me'));

    expect($response->json('user'))->not->toHaveKey('password')
        ->and($response->json('user'))->not->toHaveKey('two_factor_secret');
});

/**
 * The app has to be able to read its context before it can pick a company, so
 * `/me` deliberately sits outside the company gate.
 */
it('is readable by a user who has no company yet', function () {
    $user = User::factory()->withSubscription()->create();

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.me'))
        ->assertSuccessful()
        ->assertJsonPath('companies.all', [])
        ->assertJsonPath('companies.current_id', null);
});

/**
 * And before they have bought anything — otherwise a lapsed account could not
 * even reach the screen telling it to subscribe.
 */
it('is readable by a user with no active plan', function () {
    $user = User::factory()->create();

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.me'))
        ->assertSuccessful()
        ->assertJsonPath('subscription.plan', null);
});

it('lists every company the user belongs to and no others', function () {
    [$user] = apiContext();

    $second = Company::factory()->create(['name' => 'Second Ltd']);
    $user->companies()->attach($second->id, ['is_owner' => false, 'status' => 'active']);

    Company::factory()->create(['name' => 'Not mine']);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.companies.index'));

    $response->assertSuccessful()->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('name')->all())
        ->not->toContain('Not mine');
});

it('lists only active currencies', function () {
    [$user] = apiContext();

    Currency::query()->updateOrCreate(['code' => 'USD'], [
        'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'is_active' => true,
    ]);
    Currency::query()->updateOrCreate(['code' => 'XOF'], [
        'name' => 'West African Franc', 'symbol' => 'CFA', 'precision' => 0, 'is_active' => false,
    ]);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.currencies.index'));

    $response->assertSuccessful();

    expect(collect($response->json('data'))->pluck('code')->all())
        ->toContain('USD')
        ->not->toContain('XOF');
});

/* --------------------------------------------------- Subscription gate -- */

it('answers the tenant app with a payment required status when there is no plan', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company->id, ['is_owner' => true, 'status' => 'active']);
    $user->forceFill(['current_company_id' => $company->id])->save();

    $this->withHeaders(apiHeaders($user))
        ->getJson(route('api.v1.clients.index'))
        ->assertStatus(402)
        ->assertJsonPath('error', 'subscription_required');
});
