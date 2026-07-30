<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\User;

beforeEach(function () {
    Currency::create(['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true]);
});

test('a company with only the required fields is a third complete', function () {
    $company = Company::factory()->create([
        'email' => null,
        'phone' => null,
        'tpin' => null,
        'address' => null,
        'logo_path' => null,
        'primary_color' => null,
    ]);

    expect($company->profileCompletion())->toBe([
        'filled' => 3,
        'total' => 9,
        'percent' => 33,
    ]);
});

test('a company with every field filled is fully complete', function () {
    $company = Company::factory()->create([
        'email' => 'billing@acme.test',
        'phone' => '+260970000000',
        'tpin' => '1001234567',
        'address' => 'Lusaka, Zambia',
        'logo_path' => 'company-logos/acme.png',
        'primary_color' => '#00417d',
    ]);

    expect($company->profileCompletion())->toBe([
        'filled' => 9,
        'total' => 9,
        'percent' => 100,
    ]);
});

test('whitespace-only fields do not count as filled', function () {
    $company = Company::factory()->create([
        'email' => '   ',
        'phone' => '',
        'tpin' => null,
        'address' => "\t",
        'logo_path' => null,
        'primary_color' => null,
    ]);

    expect($company->profileCompletion()['filled'])->toBe(3);
});

test('the percentage rounds to a whole number', function () {
    $company = Company::factory()->create([
        'email' => 'billing@acme.test',
        'phone' => '+260970000000',
        'tpin' => null,
        'address' => null,
        'logo_path' => null,
        'primary_color' => null,
    ]);

    expect($company->profileCompletion())->toBe([
        'filled' => 5,
        'total' => 9,
        'percent' => 56,
    ]);
});

test('the companies index exposes profile completion for each company', function () {
    $user = User::factory()->withSubscription()->create();

    $company = Company::factory()->create([
        'owner_id' => $user->id,
        'email' => 'billing@acme.test',
        'phone' => null,
        'tpin' => null,
        'address' => null,
        'logo_path' => null,
        'primary_color' => null,
    ]);

    $company->users()->attach($user->id, ['is_owner' => true, 'status' => 'active']);

    $this->actingAs($user)
        ->get('/companies')
        ->assertInertia(fn ($page) => $page
            ->component('Companies/Index')
            ->where('companies.0.profile_completion.filled', 4)
            ->where('companies.0.profile_completion.total', 9)
            ->where('companies.0.profile_completion.percent', 44)
        );
});
