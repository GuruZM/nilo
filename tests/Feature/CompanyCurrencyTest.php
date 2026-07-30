<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Currency::create(['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true]);
    Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'is_active' => true]);
    Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'is_active' => false]);
});

/**
 * Attach the user to the company as an active owner and make it their current one.
 */
function activateCompanyFor(User $user, Company $company): Company
{
    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);

    $user->forceFill(['current_company_id' => $company->id])->save();

    return $company;
}

test('creating a company persists the chosen billing currency', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Dollar Co',
            'type' => 'services',
            'currency_code' => 'USD',
        ])
        ->assertSessionHasNoErrors();

    expect(Company::where('name', 'Dollar Co')->value('currency_code'))->toBe('USD');
});

test('a lowercase currency code is stored uppercased', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Lowercase Co',
            'type' => 'services',
            'currency_code' => 'usd',
        ])
        ->assertSessionHasNoErrors();

    expect(Company::where('name', 'Lowercase Co')->value('currency_code'))->toBe('USD');
});

test('creating a company requires a billing currency', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', ['name' => 'No Currency Co', 'type' => 'services'])
        ->assertSessionHasErrors('currency_code');

    expect(Company::where('name', 'No Currency Co')->exists())->toBeFalse();
});

test('creating a company rejects an unknown currency', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Fake Currency Co',
            'type' => 'services',
            'currency_code' => 'XYZ',
        ])
        ->assertSessionHasErrors('currency_code');

    expect(Company::where('name', 'Fake Currency Co')->exists())->toBeFalse();
});

test('creating a company rejects an inactive currency', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Inactive Currency Co',
            'type' => 'services',
            'currency_code' => 'EUR',
        ])
        ->assertSessionHasErrors('currency_code');

    expect(Company::where('name', 'Inactive Currency Co')->exists())->toBeFalse();
});

test('a member can change the billing currency of their company', function () {
    $user = User::factory()->withSubscription()->create();
    $company = activateCompanyFor($user, Company::factory()->create(['owner_id' => $user->id]));

    $this->actingAs($user)
        ->put(route('companies.update', $company), [
            'name' => $company->name,
            'type' => 'services',
            'currency_code' => 'USD',
        ])
        ->assertSessionHasNoErrors();

    expect($company->refresh()->currency_code)->toBe('USD');
});

test('new invoices default to the company currency, not the user currency', function () {
    $user = User::factory()->withSubscription()->create();
    $user->forceFill(['current_currency_code' => 'ZMW'])->save();

    $company = activateCompanyFor($user, Company::factory()->create([
        'owner_id' => $user->id,
        'currency_code' => 'USD',
    ]));

    Client::create(['company_id' => $company->id, 'name' => 'Client One']);
    InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Default',
        'type' => 'invoice',
        'is_default' => true,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->where('defaultCurrencyCode', 'USD')
        );
});

test('new quotations default to the company currency, not the user currency', function () {
    $user = User::factory()->withSubscription()->create();
    $user->forceFill(['current_currency_code' => 'ZMW'])->save();

    $company = activateCompanyFor($user, Company::factory()->create([
        'owner_id' => $user->id,
        'currency_code' => 'USD',
    ]));

    Client::create(['company_id' => $company->id, 'name' => 'Client One']);
    InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Default',
        'type' => 'quotation',
        'is_default' => true,
    ]);

    $this->actingAs($user)
        ->get(route('quotations.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quotations/Create')
            ->where('defaultCurrencyCode', 'USD')
        );
});

test('the document currency default falls back to the user currency without a company', function () {
    expect(Company::defaultCurrencyCodeFor(null, 'usd'))->toBe('USD');
});

test('the document currency default falls back to ZMW with nothing to go on', function () {
    expect(Company::defaultCurrencyCodeFor(null, null))->toBe('ZMW');
});
