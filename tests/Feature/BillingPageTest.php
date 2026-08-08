<?php

use App\Models\Payment;
use App\Models\Subscription;
use Inertia\Testing\AssertableInertia;

it('shows the plan a subscriber is on, what it costs and when it runs out', function () {
    $user = activeSubscriber();

    $this->actingAs($user)->get('/subscription')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('subscription/current')
            ->where('subscription.plan.slug', 'standard')
            ->where('subscription.plan.price', '100000.00')
            ->where('subscription.plan.billing_period', 'monthly')
            ->where('subscription.status', 'active')
            ->whereNot('subscription.ends_at', null)
            ->has('usage.purchase_orders')
            ->where('pendingSubscription', null)
    );
});

it('separates an upgrade waiting on payment from the plan still serving', function () {
    $user = activeSubscriber();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);

    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $premium->id,
        'status' => 'pending_payment',
        'starts_at' => now(),
        'payment_method' => 'dpo',
    ]);

    $this->actingAs($user)->get('/subscription')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('subscription.plan.slug', 'standard')
            ->where('pendingSubscription.plan.slug', 'premium')
    );
});

it('lists the subscriber\'s own payments and nobody else\'s', function () {
    $user = activeSubscriber();
    $stranger = activeSubscriber();
    $plan = $user->activePlan();

    $mine = Payment::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    $theirs = Payment::factory()->create(['user_id' => $stranger->id, 'plan_id' => $plan->id]);

    $this->actingAs($user)->get('/subscription')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $mine->id)
            ->where('payments.data.0.plan.name', $plan->name)
    );

    expect($theirs->user_id)->not->toBe($user->id);
});

/**
 * The payment row carries an admin's private notes and a live gateway token,
 * neither of which belongs on a page the customer can open.
 */
it('keeps internal payment columns off the billing page', function () {
    $user = activeSubscriber();

    Payment::factory()->dpo()->create([
        'user_id' => $user->id,
        'plan_id' => $user->activePlan()->id,
        'admin_notes' => 'Chased twice, seems dodgy.',
        'dpo_transaction_token' => 'TOKEN123',
        'pop_file_path' => 'payments/pop/secret.png',
        'phone_number' => '0977123456',
    ]);

    $this->actingAs($user)->get('/subscription')->assertInertia(
        fn (AssertableInertia $page) => $page->has(
            'payments.data.0',
            fn (AssertableInertia $row) => $row
                ->missing('admin_notes')
                ->missing('dpo_transaction_token')
                ->missing('gateway_response')
                ->missing('pop_file_path')
                ->missing('phone_number')
                ->missing('confirmed_by')
                ->etc()
        )
    );
});

it('pages the payment history ten at a time', function () {
    $user = activeSubscriber();
    Payment::factory()->count(12)->create([
        'user_id' => $user->id,
        'plan_id' => $user->activePlan()->id,
    ]);

    $this->actingAs($user)->get('/subscription')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('payments.data', 10));

    $this->actingAs($user)->get('/subscription?page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('payments.data', 2));
});

/**
 * The plan picker's back link keys off currentPlan: a subscriber arrived from
 * Billing and must be able to change their mind, while a new user was sent here
 * by the subscription gate and has nowhere to go back to yet.
 */
it('tells the plan picker which plan the subscriber already holds', function () {
    $this->actingAs(activeSubscriber())->get('/subscription/select')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('subscription/select')
            ->where('currentPlan.slug', 'standard')
    );
});

it('reports no current plan to the picker for a user the gate sent there', function () {
    dpoPlan();

    $this->actingAs(dpoBuyer())->get('/subscription/select')->assertInertia(
        fn (AssertableInertia $page) => $page->where('currentPlan', null)
    );
});

it('renders for a user who has no plan at all', function () {
    $this->actingAs(dpoBuyer())->get('/subscription')
        ->assertSuccessful()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('subscription', null)
                ->where('pendingSubscription', null)
                ->has('payments.data', 0)
        );
});
