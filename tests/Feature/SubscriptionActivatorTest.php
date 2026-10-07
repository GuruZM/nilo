<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Subscription;
use App\Services\SubscriptionActivator;

/**
 * These pin the behaviour that used to live inside Admin\PaymentController, so
 * the extraction is provably non-regressive — and add the guard the callbacks
 * need that a button click never did.
 */
it('activates a payment once and refuses to do it twice', function () {
    $activator = app(SubscriptionActivator::class);
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());

    expect($activator->activate($payment))->toBeTrue();

    $endsAt = $payment->fresh()->subscription->ends_at;
    $confirmedAt = $payment->fresh()->confirmed_at;

    $this->travel(1)->hour();

    expect($activator->activate($payment->fresh()))->toBeFalse()
        ->and($payment->fresh()->subscription->ends_at->toIso8601String())->toBe($endsAt->toIso8601String())
        ->and($payment->fresh()->confirmed_at->toIso8601String())->toBe($confirmedAt->toIso8601String());
});

it('cancels the subscription and hands the coupon back on rejection', function () {
    $activator = app(SubscriptionActivator::class);
    $user = dpoBuyer();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(20)->create(['redemptions_count' => 1]);
    $payment = pendingDpoPayment($user, $plan, $coupon);

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'payment_id' => $payment->id,
        'discount_amount' => 20000,
        'currency_code' => 'ZMW',
    ]);

    $activator->reject($payment);

    $payment->refresh();

    expect($payment->status)->toBe('rejected')
        ->and($payment->subscription->status)->toBe('cancelled')
        ->and($payment->subscription->cancelled_at)->not->toBeNull()
        ->and($coupon->fresh()->redemptions_count)->toBe(0)
        ->and(CouponRedemption::count())->toBe(0);
});

it('records who settled a payment by hand', function () {
    $activator = app(SubscriptionActivator::class);
    $admin = dpoBuyer();
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());

    $activator->activate($payment, $admin, 'Matched against the bank statement.');

    $payment->refresh();

    expect($payment->confirmed_by)->toBe($admin->id)
        ->and($payment->admin_notes)->toBe('Matched against the bank statement.');
});

/**
 * An upgrade bought mid-period leaves two active rows unless settlement closes
 * the old one out. These pin that, and pin that the closing-out is as careful
 * about replays as the activation itself.
 */
it('closes out the plan an upgrade replaces', function () {
    $activator = app(SubscriptionActivator::class);
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium', 'price' => 200000]);
    $user = activeSubscriber();
    $old = $user->activeSubscription;

    $payment = pendingDpoPayment($user, $premium);

    expect($activator->activate($payment))->toBeTrue();

    expect($old->fresh()->status)->toBe('cancelled')
        ->and($old->fresh()->cancelled_at)->not->toBeNull()
        ->and(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1)
        ->and($user->fresh()->activePlan()->id)->toBe($premium->id);
});

it('leaves other users subscriptions alone when it closes one out', function () {
    $activator = app(SubscriptionActivator::class);
    $bystander = activeSubscriber();
    $user = activeSubscriber();

    $activator->activate(pendingDpoPayment($user, dpoPlan(['slug' => 'premium'])));

    expect($bystander->fresh()->activeSubscription->status)->toBe('active');
});

it('does not close anything out when the payment carries no subscription', function () {
    $activator = app(SubscriptionActivator::class);
    $user = activeSubscriber();
    $payment = pendingDpoPayment($user, dpoPlan(['slug' => 'premium']));

    $payment->subscription->delete();
    $payment->update(['subscription_id' => null]);

    expect($activator->activate($payment->fresh()))->toBeTrue()
        ->and($user->fresh()->activeSubscription->status)->toBe('active');
});

it('does not re-close a superseded plan when the callback is replayed', function () {
    $activator = app(SubscriptionActivator::class);
    $user = activeSubscriber();
    $old = $user->activeSubscription;
    $payment = pendingDpoPayment($user, dpoPlan(['slug' => 'premium']));

    $activator->activate($payment);
    $cancelledAt = $old->fresh()->cancelled_at;

    $this->travel(1)->hour();

    expect($activator->activate($payment->fresh()))->toBeFalse()
        ->and($old->fresh()->cancelled_at->toIso8601String())->toBe($cancelledAt->toIso8601String());
});

it('closes out the replaced plan when a coupon covers the new one in full', function () {
    $activator = app(SubscriptionActivator::class);
    $user = activeSubscriber();
    $old = $user->activeSubscription;
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);
    $coupon = Coupon::factory()->percentage(100)->create();

    $activator->activateFree($user, $premium, $coupon, 200000.0);

    expect($old->fresh()->status)->toBe('cancelled')
        ->and(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1)
        ->and($user->fresh()->activePlan()->id)->toBe($premium->id);
});

it('starts a coupon-covered subscription with a zero-value payment on file', function () {
    $activator = app(SubscriptionActivator::class);
    $user = dpoBuyer();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(100)->create();

    $subscription = $activator->activateFree($user, $plan, $coupon, 100000.0);

    expect($subscription->status)->toBe('active')
        ->and($subscription->ends_at->toDateString())->toBe(now()->addMonth()->toDateString());

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'payment_method' => 'coupon',
        'status' => 'confirmed',
        'amount' => 0,
    ]);

    expect(CouponRedemption::count())->toBe(1);
});

it('brings a paused subscriber back when they pay again', function () {
    $activator = app(SubscriptionActivator::class);
    $user = dpoBuyer();
    $plan = dpoPlan();
    $paused = Subscription::factory()->paused()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    $payment = pendingDpoPayment($user, $plan);

    $activator->activate($payment);

    expect($paused->fresh())
        ->status->toBe('cancelled')
        ->cancelled_at->not->toBeNull();

    expect($user->fresh()->hasActiveSubscription())->toBeTrue()
        ->and($user->fresh()->activeSubscription->id)->toBe($payment->subscription_id);
});
