<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * @return array<string, mixed>
 */
function planPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Growth',
        'slug' => 'growth',
        'description' => 'For teams outgrowing Standard.',
        'price' => 250000,
        'currency_code' => 'ZMW',
        'billing_period' => 'monthly',
        'max_companies' => 5,
        'max_invoices' => 50,
        'max_quotations' => 50,
        'max_purchase_orders' => 50,
        'max_invoice_templates' => 10,
        'max_quotation_templates' => 10,
        'can_upload_custom_template' => true,
        'is_active' => true,
        'is_public' => true,
        'is_popular' => false,
        'sort_order' => 2,
        'features' => ['5 Companies', '50 Invoices'],
    ], $overrides);
}

function admin(): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('super-admin');

    return $user;
}

it('lists plans with their active subscriber counts', function () {
    $plan = Plan::factory()->create(['name' => 'Standard']);
    Subscription::factory()->count(2)->create([
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);
    Subscription::factory()->create([
        'plan_id' => $plan->id,
        'status' => 'cancelled',
    ]);

    $this->actingAs(admin())
        ->get(route('admin.plans.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/plans/index')
            ->where('plans.0.name', 'Standard')
            ->where('plans.0.subscriptions_count', 2)
        );
});

it('creates a plan that immediately shows on the welcome page', function () {
    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload())
        ->assertRedirect(route('admin.plans.index'));

    $plan = Plan::where('slug', 'growth')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->max_companies)->toBe(5)
        ->and($plan->features)->toBe(['5 Companies', '50 Invoices']);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => collect($plans)->contains('slug', 'growth'))
        );
});

it('hides an inactive plan from the welcome page but keeps it in admin', function () {
    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload(['is_active' => false]));

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => ! collect($plans)->contains('slug', 'growth'))
        );

    $this->actingAs(admin())
        ->get(route('admin.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => collect($plans)->contains('slug', 'growth'))
        );
});

it('creates an unlisted plan that stays off the welcome page but remains in admin', function () {
    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload(['is_public' => false]));

    expect(Plan::where('slug', 'growth')->first())
        ->is_active->toBeTrue()
        ->is_public->toBeFalse();

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => ! collect($plans)->contains('slug', 'growth'))
        );

    $this->actingAs(admin())
        ->get(route('admin.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->where('plans', fn ($plans) => collect($plans)->contains('slug', 'growth'))
        );
});

it('refuses the popular badge to an unlisted plan', function () {
    $listed = Plan::factory()->create(['is_popular' => true]);

    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload([
            'is_public' => false,
            'is_popular' => true,
        ]));

    expect(Plan::where('slug', 'growth')->first()->is_popular)->toBeFalse()
        ->and($listed->fresh()->is_popular)->toBeTrue();
});

it('strips blank feature rows left behind by the repeater', function () {
    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload([
            'features' => ['Real feature', '', '   ', 'Another'],
        ]));

    expect(Plan::where('slug', 'growth')->first()->features)
        ->toBe(['Real feature', 'Another']);
});

it('lets only one plan hold the popular badge', function () {
    $existing = Plan::factory()->create(['is_popular' => true]);

    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload(['is_popular' => true]));

    expect(Plan::where('slug', 'growth')->first()->is_popular)->toBeTrue()
        ->and($existing->fresh()->is_popular)->toBeFalse();
});

it('rejects a duplicate slug', function () {
    Plan::factory()->create(['slug' => 'growth']);

    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload())
        ->assertSessionHasErrors('slug');
});

it('rejects invalid input', function (array $overrides, string $field) {
    $this->actingAs(admin())
        ->post(route('admin.plans.store'), planPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'uppercase slug' => [['slug' => 'Growth Plan'], 'slug'],
    'negative price' => [['price' => -5], 'price'],
    'limit below unlimited' => [['max_invoices' => -2], 'max_invoices'],
    'unknown billing period' => [['billing_period' => 'weekly'], 'billing_period'],
]);

it('updates a plan but refuses to change its slug', function () {
    $plan = Plan::factory()->create(['slug' => 'growth', 'name' => 'Growth']);

    $this->actingAs(admin())
        ->put(route('admin.plans.update', $plan), planPayload([
            'name' => 'Growth Plus',
            'slug' => 'something-else',
        ]))
        ->assertRedirect(route('admin.plans.index'));

    expect($plan->fresh()->name)->toBe('Growth Plus')
        ->and($plan->fresh()->slug)->toBe('growth');
});

it('deletes a plan that nobody is subscribed to', function () {
    $plan = Plan::factory()->create();

    $this->actingAs(admin())
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect(route('admin.plans.index'));

    expect(Plan::find($plan->id))->toBeNull();
});

it('refuses to delete a plan that still has subscriptions', function () {
    $plan = Plan::factory()->create();
    Subscription::factory()->create(['plan_id' => $plan->id]);

    $this->actingAs(admin())
        ->delete(route('admin.plans.destroy', $plan))
        ->assertSessionHas('error');

    expect(Plan::find($plan->id))->not->toBeNull();
});

it('keeps the whole plans area behind the admin role', function (string $method, string $uri) {
    $user = User::factory()->withSubscription()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->call($method, $uri)->assertForbidden();
})->with([
    'index' => ['get', '/admin/plans'],
    'create' => ['get', '/admin/plans/create'],
    'store' => ['post', '/admin/plans'],
]);
