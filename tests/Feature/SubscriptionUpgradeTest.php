<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Sleep::fake();
    config(['services.dpo.enabled' => true, 'services.dpo.company_token' => 'TEST']);
});

/**
 * Checkout files a second subscription row and only settles it later, so for as
 * long as the payment is in flight the account has two. These pin that the plan
 * already paid for keeps serving the customer throughout — before this, the
 * pending row shadowed it and the subscribed middleware turned a paying
 * customer out of the app halfway through paying.
 */
it('keeps the current plan serving while an upgrade is at the gateway', function () {
    fakeCreateToken();
    $user = activeSubscriber();
    $standard = $user->activePlan();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium', 'price' => 200000]);

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $premium->id]);

    $this->actingAs($user)->get('/dashboard')->assertSuccessful();

    expect($user->fresh()->activePlan()->id)->toBe($standard->id)
        ->and($user->fresh()->pendingSubscription->plan_id)->toBe($premium->id);
});

it('still reports the paid-for plan to the frontend during a checkout', function () {
    fakeCreateToken();
    $user = activeSubscriber();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $premium->id]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('subscription.status', 'active')
            ->where('subscription.plan.slug', 'standard')
    );
});

it('leaves the current plan intact when an upgrade is declined', function () {
    $user = activeSubscriber();
    $standard = $user->activePlan();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);
    $payment = pendingDpoPayment($user, $premium);
    fakeVerify('904', 'Cancelled');

    $this->actingAs($user)->get('/subscription/payment/dpo/cancel?TransactionToken=TOKEN123');

    expect($payment->fresh()->subscription->status)->toBe('cancelled')
        ->and($user->fresh()->activePlan()->id)->toBe($standard->id)
        ->and(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);

    $this->actingAs($user)->get('/dashboard')->assertSuccessful();
});

it('swaps the customer onto the new plan once the upgrade settles', function () {
    $user = activeSubscriber();
    $old = $user->activeSubscription;
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium', 'price' => 200000]);
    pendingDpoPayment($user, $premium);
    fakeVerify('000', 'Transaction Paid');

    $this->actingAs($user)->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123');

    $user->refresh();

    expect($user->activePlan()->id)->toBe($premium->id)
        ->and($old->fresh()->status)->toBe('cancelled')
        ->and(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1)
        // No proration: the new plan runs a full period from settlement.
        ->and($user->activeSubscription->ends_at->toDateString())->toBe(now()->addMonth()->toDateString());
});

it('keeps the current plan serving while a manual transfer is reviewed', function () {
    $user = activeSubscriber();
    $standard = $user->activePlan();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);

    $this->actingAs($user)->post('/subscription/payment', [
        'plan_id' => $premium->id,
        'payment_method' => 'mobile_money',
        'payment_reference' => 'TX123456',
    ])->assertRedirect('/subscription/payment-status');

    $this->actingAs($user)->get('/dashboard')->assertSuccessful();

    expect($user->fresh()->activePlan()->id)->toBe($standard->id);

    app(App\Services\SubscriptionActivator::class)->activate(Payment::latest('id')->first());

    expect($user->fresh()->activePlan()->id)->toBe($premium->id)
        ->and(Subscription::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);
});

/**
 * There is no auto-renewal anywhere in the product, so buying the plan you are
 * already on is the only way a subscriber can extend it. Blocking that as a
 * "you already have this plan" duplicate would strand everyone at expiry.
 */
it('lets a subscriber buy the plan they are already on again', function () {
    $user = activeSubscriber();
    $plan = $user->activePlan();

    $this->actingAs($user)
        ->post('/subscription/subscribe', ['plan_id' => $plan->id])
        ->assertRedirect('/subscription/payment/'.$plan->id);
});

it('refuses to drop an active subscriber onto the free plan', function () {
    $user = activeSubscriber();
    $free = dpoPlan(['slug' => 'free', 'name' => 'Free', 'price' => 0]);

    $this->actingAs($user)
        ->from('/subscription/select')
        ->post('/subscription/subscribe', ['plan_id' => $free->id])
        ->assertRedirect('/subscription/select')
        ->assertSessionHas('error');

    expect($user->fresh()->activePlan()->slug)->toBe('standard')
        ->and(Subscription::where('user_id', $user->id)->count())->toBe(1);
});

it('still enrols a user with no plan on the free tier', function () {
    $free = dpoPlan(['slug' => 'free', 'name' => 'Free', 'price' => 0]);

    $this->actingAs(dpoBuyer())
        ->post('/subscription/subscribe', ['plan_id' => $free->id])
        ->assertRedirect('/dashboard');

    expect(Subscription::where('plan_id', $free->id)->where('status', 'active')->count())->toBe(1);
});

/**
 * The free plan has no price to charge, so a checkout for it can only be a
 * crafted request — today it would have filed a zero-value pending payment for
 * an admin to puzzle over.
 */
it('refuses to take a payment for the free plan', function (string $entryPoint) {
    $free = Plan::factory()->create([
        'slug' => 'free',
        'name' => 'Free',
        'price' => 0,
        'is_active' => true,
        'is_public' => true,
    ]);

    $request = $this->actingAs(dpoBuyer());

    $response = match ($entryPoint) {
        'page' => $request->get('/subscription/payment/'.$free->id),
        'manual' => $request->post('/subscription/payment', [
            'plan_id' => $free->id,
            'payment_method' => 'mobile_money',
            'payment_reference' => 'TX123456',
        ]),
        'gateway' => $request->post('/subscription/payment/dpo', ['plan_id' => $free->id]),
    };

    $response->assertForbidden();

    expect(Payment::count())->toBe(0);
})->with(['page', 'manual', 'gateway']);
