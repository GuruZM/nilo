<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    config(['services.dpo.enabled' => true, 'services.dpo.company_token' => 'TEST']);
});

it('settles a payment whose customer never made it back', function () {
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());
    $payment->forceFill(['created_at' => now()->subHour()])->save();
    fakeVerify('000', 'Transaction Paid');

    $this->artisan('dpo:reconcile')->assertSuccessful();

    $payment->refresh();

    expect($payment->status)->toBe('confirmed')
        ->and($payment->gateway_status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->subscription->status)->toBe('active');
});

/**
 * A customer still typing their card details at DPO must not have their
 * transaction demoted out from under them.
 */
it('leaves a payment younger than the window alone', function () {
    Http::fake();
    pendingDpoPayment(dpoBuyer(), dpoPlan());

    $this->artisan('dpo:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

it('rejects an expired transaction and releases its coupon', function () {
    $user = dpoBuyer();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(20)->create(['redemptions_count' => 1]);
    $payment = pendingDpoPayment($user, $plan, $coupon);
    $payment->forceFill(['created_at' => now()->subDay()])->save();

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'payment_id' => $payment->id,
        'discount_amount' => 20000,
        'currency_code' => 'ZMW',
    ]);

    fakeVerify('903', 'Transaction expired');

    $this->artisan('dpo:reconcile')->assertSuccessful();

    $payment->refresh();

    expect($payment->status)->toBe('rejected')
        ->and($payment->gateway_status)->toBe('expired')
        ->and($payment->subscription->status)->toBe('cancelled')
        ->and($coupon->fresh()->redemptions_count)->toBe(0)
        ->and(CouponRedemption::count())->toBe(0);
});

/**
 * One unreachable transaction must not strand every other customer queued
 * behind it in the same run.
 */
it('keeps going when one payment cannot be checked', function () {
    Log::spy();

    $plan = dpoPlan();
    $stuck = pendingDpoPayment(dpoBuyer(), $plan);
    $stuck->forceFill(['created_at' => now()->subHours(2)])->save();

    $good = pendingDpoPayment(dpoBuyer(), $plan, token: 'TOKEN456');
    $good->forceFill(['created_at' => now()->subHour()])->save();

    // `latest()` puts the newer row first, so the failure lands mid-run.
    Http::fakeSequence()
        ->push('nope', 500)
        ->push('nope', 500)
        ->push('nope', 500)
        ->push(dpoResponse('<Result>000</Result>'), 200);

    $this->artisan('dpo:reconcile --minutes=30')->assertSuccessful();

    expect($stuck->fresh()->status)->toBe('confirmed')
        ->and($good->fresh()->status)->toBe('pending');

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message) => $message === 'DPO reconciliation failed'
    )->once();
});

it('never touches a manually settled payment', function () {
    Http::fake();

    $payment = Payment::factory()->create([
        'user_id' => dpoBuyer()->id,
        'plan_id' => dpoPlan()->id,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('dpo:reconcile')->assertSuccessful();

    expect($payment->fresh()->status)->toBe('pending');
    Http::assertNothingSent();
});

it('skips a payment that has already been settled', function () {
    Http::fake();

    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());
    $payment->forceFill([
        'created_at' => now()->subDay(),
        'status' => 'confirmed',
        'gateway_status' => 'paid',
    ])->save();

    $this->artisan('dpo:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});
