<?php

use App\Models\Currency;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Currency::create(['code' => 'ZMW', 'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true]);
    Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'is_active' => true]);
    Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'is_active' => false]);
});

/**
 * The settings page must not shadow the shared `currencies` prop, which the
 * sidebar switcher reads on every page.
 */
it('keeps the shared currency prop intact on the currencies page', function () {
    $user = User::factory()->withSubscription()->create([
        'current_currency_code' => 'USD',
    ]);

    $this->actingAs($user)
        ->get('/settings/currencies')
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('currencies.all', 2)
                ->where('currencies.current.code', 'USD')
        );
});

it('serves the full catalog under its own prop name', function () {
    $user = User::factory()->withSubscription()->create([
        'current_currency_code' => 'USD',
    ]);

    $this->actingAs($user)
        ->get('/settings/currencies')
        ->assertInertia(fn (Assert $page) => $page->has('catalog', 3));
});

it('leaves the switcher usable after visiting the currencies page', function () {
    $user = User::factory()->withSubscription()->create([
        'current_currency_code' => 'ZMW',
    ]);

    $this->actingAs($user)
        ->get('/settings/currencies')
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            // The switcher disables itself when it sees one option or fewer.
            expect($props['currencies']['all'])->toHaveCount(2)
                ->and($props['currencies']['current']['code'])->toBe('ZMW');
        });
});
