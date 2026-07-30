<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the clients page renders for a user without a company', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->has('clients', 0)
            ->where('hasActiveCompany', false)
        );
});

test('the clients page falls back to the first company when none is active', function () {
    $user = User::factory()->withSubscription()->create();

    $company = Company::create([
        'owner_id' => $user->id,
        'name' => 'Acme Co',
        'slug' => 'acme-co',
    ]);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);

    Client::create([
        'company_id' => $company->id,
        'name' => 'Client One',
    ]);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->has('clients', 1)
            ->where('clients.0.name', 'Client One')
            ->where('hasActiveCompany', true)
        );

    expect($user->refresh()->current_company_id)->toBe($company->id);
});

test('the clients page only lists clients for the active company', function () {
    $user = User::factory()->withSubscription()->create();

    $company = Company::create([
        'owner_id' => $user->id,
        'name' => 'Acme Co',
        'slug' => 'acme-co',
    ]);

    $otherCompany = Company::create([
        'owner_id' => $user->id,
        'name' => 'Other Co',
        'slug' => 'other-co',
    ]);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);
    $user->forceFill(['current_company_id' => $company->id])->save();

    Client::create(['company_id' => $company->id, 'name' => 'Mine']);
    Client::create(['company_id' => $otherCompany->id, 'name' => 'Not Mine']);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->has('clients', 1)
            ->where('clients.0.name', 'Mine')
        );
});
