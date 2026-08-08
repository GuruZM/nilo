<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function dpoAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    return $admin;
}

beforeEach(function () {
    Sleep::fake();
    $this->seed(Database\Seeders\RolePermissionSeeder::class);
    config(['services.dpo.enabled' => true, 'services.dpo.company_token' => 'TEST']);
});

/**
 * DPO's verify response carries card metadata. The admin chasing a failed
 * charge needs it; the subscriber's own status page must never ship it.
 */
it('shows the raw gateway response to an admin but not to the subscriber', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());
    $payment->forceFill(['gateway_response' => ['Result' => '901', 'CardMask' => '4111********1111']])->save();

    $this->actingAs(dpoAdmin())
        ->get("/admin/payments/{$payment->id}")
        ->assertInertia(fn ($page) => $page->where('payment.gateway_response.CardMask', '4111********1111'));

    $this->actingAs($user)
        ->get('/subscription/payment-status')
        ->assertInertia(fn ($page) => $page->missing('payment.gateway_response'));
});

it('refuses to let an admin hand-confirm a gateway payment', function () {
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());

    $this->actingAs(dpoAdmin())
        ->post("/admin/payments/{$payment->id}/confirm")
        ->assertForbidden();

    $payment->refresh();

    expect($payment->status)->toBe('pending')
        ->and($payment->subscription->status)->toBe('pending_payment');
});

it('refuses to let an admin hand-reject a gateway payment', function () {
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

    $this->actingAs(dpoAdmin())
        ->post("/admin/payments/{$payment->id}/reject")
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($coupon->fresh()->redemptions_count)->toBe(1)
        ->and(CouponRedemption::count())->toBe(1);
});

it('lets an admin re-ask DPO about a stuck payment', function () {
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());
    fakeVerify('000', 'Transaction Paid');

    $this->actingAs(dpoAdmin())
        ->from("/admin/payments/{$payment->id}")
        ->post("/admin/payments/{$payment->id}/verify")
        ->assertRedirect("/admin/payments/{$payment->id}")
        ->assertSessionHas('success');

    $payment->refresh();

    expect($payment->status)->toBe('confirmed')
        ->and($payment->subscription->status)->toBe('active');
});

it('has nothing to verify on a manual payment', function () {
    $payment = Payment::factory()->create([
        'user_id' => dpoBuyer()->id,
        'plan_id' => dpoPlan()->id,
        'payment_method' => 'bank_transfer',
    ]);

    $this->actingAs(dpoAdmin())
        ->post("/admin/payments/{$payment->id}/verify")
        ->assertNotFound();
});

it('filters the payment ledger by settlement route', function () {
    $plan = dpoPlan();
    $gateway = pendingDpoPayment(dpoBuyer(), $plan);
    $manual = Payment::factory()->create([
        'user_id' => dpoBuyer()->id,
        'plan_id' => $plan->id,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
    ]);

    $this->actingAs(dpoAdmin())
        ->get('/admin/payments?method=dpo')
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $gateway->id)
        );

    $this->actingAs(dpoAdmin())
        ->get('/admin/payments?method=manual')
        ->assertInertia(fn ($page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.id', $manual->id)
        );
});

it('still lets an admin confirm a manual payment', function () {
    Http::fake();

    $payment = Payment::factory()->create([
        'user_id' => dpoBuyer()->id,
        'plan_id' => dpoPlan()->id,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
    ]);

    $this->actingAs(dpoAdmin())
        ->post("/admin/payments/{$payment->id}/confirm")
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('confirmed');
});
