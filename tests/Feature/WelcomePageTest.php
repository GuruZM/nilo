<?php

use App\Models\Plan;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the welcome page with active plans ordered by sort_order', function () {
    Plan::factory()->create(['name' => 'Second', 'sort_order' => 1, 'is_active' => true]);
    Plan::factory()->create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);
    Plan::factory()->create(['name' => 'Hidden', 'sort_order' => 2, 'is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('plans', 2)
            ->where('plans.0.name', 'First')
            ->where('plans.1.name', 'Second')
        );
});

it('passes the admin defined plan copy, limits, and features to the pricing section', function () {
    Plan::factory()->create([
        'name' => 'Standard',
        'description' => 'For growing businesses that need more capacity.',
        'price' => 100000,
        'currency_code' => 'ZMW',
        'billing_period' => 'monthly',
        'max_companies' => 2,
        'max_invoices' => 20,
        'max_quotations' => -1,
        'max_invoice_templates' => 3,
        'max_quotation_templates' => 3,
        'is_popular' => true,
        'features' => ['Priority support'],
        'sort_order' => 0,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans', 1)
            ->where('plans.0.description', 'For growing businesses that need more capacity.')
            ->where('plans.0.price', '100000.00')
            ->where('plans.0.currency_code', 'ZMW')
            ->where('plans.0.billing_period', 'monthly')
            ->where('plans.0.max_companies', 2)
            ->where('plans.0.max_invoices', 20)
            ->where('plans.0.max_quotations', -1)
            ->where('plans.0.max_invoice_templates', 3)
            ->where('plans.0.max_quotation_templates', 3)
            ->where('plans.0.is_popular', true)
            ->where('plans.0.features', ['Priority support'])
        );
});

it('hides complimentary plans from the pricing section', function () {
    Plan::factory()->create(['name' => 'Standard', 'sort_order' => 0]);
    Plan::factory()->complimentary()->create(['name' => 'Complimentary', 'sort_order' => 1]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans', 1)
            ->where('plans.0.name', 'Standard')
        );
});

it('renders the welcome page when no plans exist', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('plans', 0)
        );
});
