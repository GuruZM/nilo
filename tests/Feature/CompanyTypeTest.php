<?php

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Currency;
use App\Models\User;

beforeEach(function () {
    Currency::create(['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true]);
});

test('creating a company persists the chosen business type', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Acme Traders',
            'type' => CompanyType::Products->value,
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHasNoErrors();

    $company = Company::where('name', 'Acme Traders')->firstOrFail();

    expect($company->type)->toBe(CompanyType::Products);
});

test('creating a company requires a business type', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', ['name' => 'No Type Co', 'currency_code' => 'ZMW'])
        ->assertSessionHasErrors('type');

    expect(Company::where('name', 'No Type Co')->exists())->toBeFalse();
});

test('creating a company rejects an unknown business type', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Bad Type Co',
            'type' => 'manufacturing',
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHasErrors('type');

    expect(Company::where('name', 'Bad Type Co')->exists())->toBeFalse();
});

test('a company defaults to a services business when the column is not set', function () {
    $user = User::factory()->create();

    $company = Company::create([
        'owner_id' => $user->id,
        'name' => 'Legacy Co',
        'slug' => 'legacy-co',
    ]);

    expect($company->refresh()->type)->toBe(CompanyType::Services);
});

test('a member can change the business type of their company', function () {
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create(['owner_id' => $user->id]);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->put(route('companies.update', $company), [
            'name' => $company->name,
            'type' => CompanyType::Products->value,
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHasNoErrors();

    expect($company->refresh()->type)->toBe(CompanyType::Products);
});

test('updating a company rejects an unknown business type', function () {
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create(['owner_id' => $user->id]);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->put(route('companies.update', $company), [
            'name' => $company->name,
            'type' => 'manufacturing',
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHasErrors('type');

    expect($company->refresh()->type)->toBe(CompanyType::Services);
});
