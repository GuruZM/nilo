<?php

use App\Enums\SubscriptionReminder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

function subscriptionAdmin(): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('super-admin');

    return $user;
}

it('shows every active plan on the user page, listed or not', function () {
    $listed = Plan::factory()->create(['name' => 'Standard']);
    $complimentary = Plan::factory()->complimentary()->create(['name' => 'Resonantt']);
    Plan::factory()->create(['name' => 'Retired', 'is_active' => false]);

    $user = User::factory()->create();

    $this->actingAs(subscriptionAdmin())
        ->get(route('admin.users.show', $user))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->where('plans', fn ($plans) => collect($plans)->pluck('id')->sort()->values()->all()
                === collect([$listed->id, $complimentary->id])->sort()->values()->all()
            )
        );
});

it('supersedes the old subscription when the plan changes', function () {
    $user = User::factory()->create();
    $premium = Plan::factory()->create(['name' => 'Premium']);
    $existing = Subscription::factory()->create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $premium->id,
            'status' => 'active',
            'ends_at' => null,
        ])
        ->assertRedirect();

    expect($existing->fresh())
        ->status->toBe('cancelled')
        ->cancelled_at->not->toBeNull();

    expect($user->fresh()->subscription)
        ->plan_id->toBe($premium->id)
        ->status->toBe('active')
        ->ends_at->toBeNull()
        ->payment_method->toBe('admin_assigned');

    expect(Subscription::where('user_id', $user->id)->count())->toBe(2);
});

it('amends the subscription in place when the plan is unchanged', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $existing = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
        'cancelled_at' => now()->subDay(),
    ]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => null,
        ]);

    expect(Subscription::where('user_id', $user->id)->count())->toBe(1);

    expect($existing->fresh())
        ->status->toBe('active')
        ->cancelled_at->toBeNull();
});

/**
 * An admin assigning a plan must not reach into a checkout the customer is
 * still part-way through at the gateway — settlement stays the only thing that
 * decides what happens to that row.
 */
it('ignores a checkout still in flight when it moves a user to another plan', function () {
    $user = User::factory()->create();
    $standard = Plan::factory()->create();
    $premium = Plan::factory()->create();
    $enterprise = Plan::factory()->create();

    $serving = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $standard->id,
        'status' => 'active',
        'ends_at' => now()->addMonth(),
    ]);
    $inFlight = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $premium->id,
        'status' => 'pending_payment',
    ]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $enterprise->id,
            'status' => 'active',
            'ends_at' => null,
        ]);

    expect($serving->fresh()->status)->toBe('cancelled')
        ->and($inFlight->fresh()->status)->toBe('pending_payment')
        ->and($user->fresh()->activePlan()->id)->toBe($enterprise->id);
});

it('sets and clears the expiry date', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'ends_at' => null,
    ]);

    $admin = subscriptionAdmin();

    $this->actingAs($admin)
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => '2027-01-31',
        ]);

    expect($user->fresh()->subscription->ends_at->toDateString())->toBe('2027-01-31');

    $this->actingAs($admin)
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => null,
        ]);

    expect($user->fresh()->subscription->ends_at)->toBeNull();
});

it('stamps cancelled_at when the status moves to cancelled', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'cancelled_at' => null,
    ]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'cancelled',
            'ends_at' => null,
        ]);

    expect($user->fresh()->subscription)
        ->status->toBe('cancelled')
        ->cancelled_at->not->toBeNull();
});

it('assigns a complimentary plan that nobody can buy', function () {
    $user = User::factory()->create();
    $resonantt = Plan::factory()->complimentary()->create(['name' => 'Resonantt']);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $resonantt->id,
            'status' => 'active',
            'ends_at' => null,
        ])
        ->assertRedirect();

    expect($user->fresh()->activePlan()->id)->toBe($resonantt->id);
});

it('creates a subscription for a user who never had one', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    expect($user->subscription)->toBeNull();

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => null,
        ]);

    expect($user->fresh()->subscription)
        ->plan_id->toBe($plan->id)
        ->status->toBe('active');
});

it('rejects invalid subscription input', function (array $overrides, string $field) {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), array_merge([
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => null,
        ], $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing plan' => [['plan_id' => null], 'plan_id'],
    'unknown plan' => [['plan_id' => 999999], 'plan_id'],
    'unknown status' => [['status' => 'suspended'], 'status'],
    'nonsense expiry' => [['ends_at' => 'whenever'], 'ends_at'],
]);

it('refuses to assign an inactive plan', function () {
    $user = User::factory()->create();
    $retired = Plan::factory()->create(['is_active' => false]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $retired->id,
            'status' => 'active',
            'ends_at' => null,
        ])
        ->assertSessionHasErrors('plan_id');
});

it('keeps subscription editing behind the admin role', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => null,
        ])
        ->assertForbidden();
});

it('lets an admin pause a subscription', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $existing = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'ends_at' => now()->addWeek(),
    ]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'paused',
            'ends_at' => $existing->ends_at->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($existing->fresh())
        ->status->toBe('paused')
        ->paused_at->not->toBeNull();
});

/**
 * Extending the due date starts a new period, which earns its own reminders.
 * Saving the same date keeps the record of what was already sent.
 */
it('resets payment reminders only when the due date moves', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();
    $existing = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'ends_at' => now()->addHours(12),
        'reminder_stage' => SubscriptionReminder::DueTomorrow,
        'reminder_sent_at' => now(),
    ]);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => $existing->ends_at->toDateString(),
        ]);

    expect($existing->fresh()->reminder_stage)->toBe(SubscriptionReminder::DueTomorrow);

    $this->actingAs(subscriptionAdmin())
        ->post(route('admin.users.subscription.update', $user), [
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

    expect($existing->fresh())
        ->reminder_stage->toBeNull()
        ->reminder_sent_at->toBeNull()
        ->paused_at->toBeNull();
});
