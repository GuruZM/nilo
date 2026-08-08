<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

function buyer(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function standardPlan(array $overrides = []): Plan
{
    return Plan::factory()->create(array_merge([
        'name' => 'Standard',
        'price' => 100000,
        'currency_code' => 'ZMW',
        'billing_period' => 'monthly',
    ], $overrides));
}

/**
 * @return array<string, mixed>
 */
function mobileMoneyPayload(Plan $plan, array $overrides = []): array
{
    return array_merge([
        'plan_id' => $plan->id,
        'payment_method' => 'mobile_money',
        'payment_reference' => 'TX123',
    ], $overrides);
}

it('shows the undiscounted total when no coupon is supplied', function () {
    $plan = standardPlan();

    $this->actingAs(buyer())
        ->get(route('subscription.payment', $plan))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('subscription/payment')
            ->where('quote.subtotal', 100000)
            ->where('quote.discount', 0)
            ->where('quote.total', 100000)
            ->where('quote.coupon', null)
            ->where('quote.error', null)
        );
});

it('quotes a percentage coupon against the plan price', function () {
    $plan = standardPlan();
    Coupon::factory()->percentage(20)->create(['code' => 'SAVE20']);

    $this->actingAs(buyer())
        ->get(route('subscription.payment', ['plan' => $plan, 'coupon' => 'save20']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('quote.discount', 20000)
            ->where('quote.total', 80000)
            ->where('quote.coupon.code', 'SAVE20')
            ->where('quote.coupon.label', '20% off')
        );
});

it('quotes a fixed coupon in the plan currency', function () {
    $plan = standardPlan();
    Coupon::factory()->fixed(25000)->create(['code' => 'FLAT25K']);

    $this->actingAs(buyer())
        ->get(route('subscription.payment', ['plan' => $plan, 'coupon' => 'FLAT25K']))
        ->assertInertia(fn ($page) => $page
            ->where('quote.discount', 25000)
            ->where('quote.total', 75000)
        );
});

it('never discounts below zero when a fixed coupon exceeds the price', function () {
    $plan = standardPlan(['price' => 10000]);
    Coupon::factory()->fixed(25000)->create(['code' => 'BIG']);

    $this->actingAs(buyer())
        ->get(route('subscription.payment', ['plan' => $plan, 'coupon' => 'BIG']))
        ->assertInertia(fn ($page) => $page
            ->where('quote.discount', 10000)
            ->where('quote.total', 0)
        );
});

it('reports an unusable code without blocking the purchase', function (callable $make, string $message) {
    $plan = standardPlan();
    $user = buyer();
    $make($plan, $user);

    $this->actingAs($user)
        ->get(route('subscription.payment', ['plan' => $plan, 'coupon' => 'TESTCODE']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('quote.error', $message)
            ->where('quote.total', 100000)
            ->where('quote.coupon', null)
        );
})->with([
    'unknown code' => [
        fn () => null,
        'That coupon code is not valid.',
    ],
    'deactivated' => [
        fn () => Coupon::factory()->create(['code' => 'TESTCODE', 'is_active' => false]),
        'That coupon is no longer available.',
    ],
    'not started' => [
        fn () => Coupon::factory()->create(['code' => 'TESTCODE', 'starts_at' => now()->addWeek()]),
        'That coupon is not active yet.',
    ],
    'expired' => [
        fn () => Coupon::factory()->expired()->create(['code' => 'TESTCODE']),
        'That coupon has expired.',
    ],
    'fully redeemed' => [
        fn () => Coupon::factory()->exhausted()->create(['code' => 'TESTCODE']),
        'That coupon has reached its redemption limit.',
    ],
    'restricted to another plan' => [
        function (Plan $plan) {
            $other = Plan::factory()->create();
            Coupon::factory()->create(['code' => 'TESTCODE'])->plans()->attach($other);
        },
        'That coupon does not apply to the Standard plan.',
    ],
    'wrong currency for a fixed discount' => [
        fn () => Coupon::factory()->fixed(500, 'USD')->create(['code' => 'TESTCODE']),
        'That coupon does not apply to the Standard plan.',
    ],
    'already used by this account' => [
        function (Plan $plan, User $user) {
            $coupon = Coupon::factory()->create(['code' => 'TESTCODE']);
            CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'discount_amount' => 100,
                'currency_code' => 'ZMW',
            ]);
        },
        'You have already used that coupon.',
    ],
]);

it('charges the discounted total and records the redemption', function () {
    $plan = standardPlan();
    $coupon = Coupon::factory()->percentage(20)->create(['code' => 'SAVE20']);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'save20']))
        ->assertRedirect(route('subscription.payment.status'));

    $payment = Payment::query()->where('user_id', $user->id)->sole();

    expect((float) $payment->amount)->toBe(80000.0)
        ->and((float) $payment->original_amount)->toBe(100000.0)
        ->and((float) $payment->discount_amount)->toBe(20000.0)
        ->and($payment->coupon_id)->toBe($coupon->id)
        ->and($payment->coupon_code)->toBe('SAVE20')
        ->and($payment->status)->toBe('pending');

    expect($coupon->fresh()->redemptions_count)->toBe(1);

    $this->assertDatabaseHas('coupon_redemptions', [
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'payment_id' => $payment->id,
        'plan_id' => $plan->id,
    ]);
});

it('leaves the payment at list price when no coupon is sent', function () {
    $plan = standardPlan();
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan))
        ->assertRedirect(route('subscription.payment.status'));

    $payment = Payment::query()->where('user_id', $user->id)->sole();

    expect((float) $payment->amount)->toBe(100000.0)
        ->and((float) $payment->discount_amount)->toBe(0.0)
        ->and($payment->coupon_id)->toBeNull();

    $this->assertDatabaseCount('coupon_redemptions', 0);
});

it('rejects a submitted purchase carrying an unusable coupon', function () {
    $plan = standardPlan();
    Coupon::factory()->expired()->create(['code' => 'OLD']);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'OLD']))
        ->assertSessionHasErrors('coupon_code');

    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('subscriptions', 0);
});

it('activates the subscription immediately when a coupon covers the whole price', function () {
    $plan = standardPlan(['billing_period' => 'yearly']);
    $coupon = Coupon::factory()->percentage(100)->create(['code' => 'FREEYEAR']);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.redeem'), ['plan_id' => $plan->id, 'coupon_code' => 'freeyear'])
        ->assertRedirect(route('dashboard'));

    $subscription = Subscription::query()->where('user_id', $user->id)->sole();
    $payment = Payment::query()->where('user_id', $user->id)->sole();

    expect($subscription->status)->toBe('active')
        ->and($subscription->payment_method)->toBe('coupon')
        ->and($subscription->ends_at->toDateString())->toBe(now()->addYear()->toDateString())
        ->and((float) $payment->amount)->toBe(0.0)
        ->and((float) $payment->discount_amount)->toBe(100000.0)
        ->and($payment->status)->toBe('confirmed')
        ->and($coupon->fresh()->redemptions_count)->toBe(1);

    expect($user->fresh()->hasActiveSubscription())->toBeTrue();
});

it('sends a partial coupon back to the payment page instead of gifting the plan', function () {
    $plan = standardPlan();
    Coupon::factory()->percentage(50)->create(['code' => 'HALF']);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.redeem'), ['plan_id' => $plan->id, 'coupon_code' => 'HALF'])
        ->assertRedirect(route('subscription.payment', ['plan' => $plan, 'coupon' => 'HALF']));

    $this->assertDatabaseCount('subscriptions', 0);
});

it('takes the zero-total shortcut even when the paid endpoint is used', function () {
    $plan = standardPlan();
    Coupon::factory()->percentage(100)->create(['code' => 'ALLFREE']);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'ALLFREE']))
        ->assertRedirect(route('dashboard'));

    expect(Subscription::query()->where('user_id', $user->id)->sole()->status)->toBe('active');
});

it('stops a capped coupon from being oversold', function () {
    $plan = standardPlan();
    $coupon = Coupon::factory()->percentage(20)->create([
        'code' => 'FIRST1',
        'max_redemptions' => 1,
    ]);

    $this->actingAs(buyer())
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'FIRST1']))
        ->assertRedirect(route('subscription.payment.status'));

    $this->actingAs(buyer())
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'FIRST1']))
        ->assertSessionHasErrors('coupon_code');

    expect($coupon->fresh()->redemptions_count)->toBe(1);
    $this->assertDatabaseCount('coupon_redemptions', 1);
});

it('lets the same account reuse a coupon that is not once-per-user', function () {
    $plan = standardPlan();
    Coupon::factory()->percentage(10)->create(['code' => 'REPEAT', 'once_per_user' => false]);
    $user = buyer();

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($user)
            ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'REPEAT']))
            ->assertRedirect(route('subscription.payment.status'));
    }

    $this->assertDatabaseCount('coupon_redemptions', 2);
});

it('claims the coupon on submission so a pending payment holds its place', function () {
    $plan = standardPlan();
    $coupon = Coupon::factory()->percentage(20)->create(['code' => 'HOLD', 'max_redemptions' => 1]);

    $this->actingAs(buyer())
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'HOLD']));

    expect($coupon->fresh()->hasRedemptionsLeft())->toBeFalse();
});

it('returns the coupon to the pool when an admin rejects the payment', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('super-admin');

    $plan = standardPlan();
    $coupon = Coupon::factory()->percentage(20)->create(['code' => 'REFUND', 'max_redemptions' => 1]);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'REFUND']));

    $payment = Payment::query()->where('user_id', $user->id)->sole();

    $this->actingAs($admin)
        ->post(route('admin.payments.reject', $payment), ['admin_notes' => 'No transfer received.'])
        ->assertRedirect();

    expect($coupon->fresh()->redemptions_count)->toBe(0);
    $this->assertDatabaseCount('coupon_redemptions', 0);
});

it('keeps the coupon spent once an admin confirms the payment', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('super-admin');

    $plan = standardPlan(['billing_period' => 'yearly']);
    $coupon = Coupon::factory()->percentage(20)->create(['code' => 'KEEP']);
    $user = buyer();

    $this->actingAs($user)
        ->post(route('subscription.payment.store'), mobileMoneyPayload($plan, ['coupon_code' => 'KEEP']));

    $payment = Payment::query()->where('user_id', $user->id)->sole();

    $this->actingAs($admin)
        ->post(route('admin.payments.confirm', $payment))
        ->assertRedirect();

    expect($coupon->fresh()->redemptions_count)->toBe(1)
        ->and($payment->fresh()->subscription->ends_at->toDateString())
        ->toBe(now()->addYear()->toDateString());
});

it('refuses to price a coupon against a plan that is not for sale', function () {
    $plan = Plan::factory()->complimentary()->create();
    Coupon::factory()->create(['code' => 'ANY']);

    $this->actingAs(buyer())
        ->get(route('subscription.payment', ['plan' => $plan, 'coupon' => 'ANY']))
        ->assertForbidden();
});
