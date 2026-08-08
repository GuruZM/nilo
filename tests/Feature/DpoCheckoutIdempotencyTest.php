<?php

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    config(['services.dpo.enabled' => true, 'services.dpo.company_token' => 'TEST']);
});

/**
 * DPO holds a transaction for its full PTL whether or not the customer comes
 * back to it, so a second checkout does not replace the first — it leaves a
 * second live, separately payable transaction behind. A customer who opens two
 * and pays both is charged twice.
 */
it('hands a repeated checkout back to the transaction DPO already holds', function () {
    fakeCreateToken();
    $user = dpoBuyer();
    $plan = dpoPlan();

    $first = $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);
    $second = $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    $url = 'https://secure.3gdirectpay.com/payv2.php?ID=TOKEN123';

    $first->assertRedirect($url);
    $second->assertRedirect($url);

    expect(Payment::count())->toBe(1)
        ->and(Subscription::count())->toBe(1);

    // The second request must not have opened anything at the gateway.
    Http::assertSentCount(1);
});

it('does not spend a coupon twice when the checkout is repeated', function () {
    fakeCreateToken();
    $user = dpoBuyer();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(20)->create();

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)->post('/subscription/payment/dpo', [
            'plan_id' => $plan->id,
            'coupon_code' => $coupon->code,
        ]);
    }

    expect(Payment::count())->toBe(1)
        ->and(CouponRedemption::count())->toBe(1)
        ->and($coupon->fresh()->redemptions_count)->toBe(1);
});

it('does not start a second free subscription when a full-cover coupon is submitted twice', function () {
    $user = dpoBuyer();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(100)->create();

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($user)->post('/subscription/payment/dpo', [
            'plan_id' => $plan->id,
            'coupon_code' => $coupon->code,
        ]);
    }

    expect(Subscription::where('status', 'active')->count())->toBe(1)
        ->and(CouponRedemption::count())->toBe(1);
});

/**
 * Reuse has to be exact. A customer who changed their mind must not be handed
 * back a token that charges them for what they changed it about — and the
 * abandoned transaction has to be closed at DPO, or it stays payable.
 */
it('retires the old transaction when the customer switches plan', function () {
    fakeCreateToken();
    $user = dpoBuyer();
    $standard = dpoPlan();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium', 'price' => 200000]);

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $standard->id]);
    $first = Payment::sole();

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $premium->id]);

    expect($first->fresh()->status)->toBe('rejected')
        ->and($first->fresh()->gateway_status)->toBe('cancelled')
        ->and($first->fresh()->subscription->status)->toBe('cancelled')
        ->and(Payment::count())->toBe(2)
        ->and(Payment::latest('id')->first()->plan_id)->toBe($premium->id);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<Request>cancelToken</Request>'));
});

it('closes the local rows even when DPO refuses the cancellation', function () {
    $user = dpoBuyer();
    $standard = dpoPlan();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);

    fakeCreateToken();
    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $standard->id]);
    $first = Payment::sole();

    Http::fake(['secure.3gdirectpay.com/*' => Http::sequence()
        ->push(dpoResponse('<Result>904</Result><ResultExplanation>Cannot cancel</ResultExplanation>'))
        ->push(dpoResponse('<Result>000</Result><TransToken>TOKEN999</TransToken><TransRef>REF999</TransRef>')),
    ]);

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $premium->id]);

    expect($first->fresh()->status)->toBe('rejected')
        ->and(Payment::count())->toBe(2);
});

it('opens a fresh transaction once the old one has outlived its PTL', function () {
    fakeCreateToken();
    config(['services.dpo.ptl_hours' => 24]);
    $user = dpoBuyer();
    $plan = dpoPlan();

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    // DPO has expired the first transaction by now, so sending the customer
    // back to it would land them on a dead page.
    $this->travel(25)->hours();

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    expect(Payment::count())->toBe(2);
});

it('turns away a checkout while one is already being opened for that customer', function () {
    $user = dpoBuyer();
    $plan = dpoPlan();

    $lock = Cache::lock("dpo:checkout:{$user->id}", 90);
    $lock->acquire();

    $this->actingAs($user)
        ->from('/subscription/payment/'.$plan->id)
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id])
        ->assertRedirect('/subscription/payment/'.$plan->id)
        ->assertSessionHas('info');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();

    $lock->release();
});

it('lets another customer check out while one customer holds their own lock', function () {
    fakeCreateToken();
    $plan = dpoPlan();
    $blocked = dpoBuyer();

    Cache::lock("dpo:checkout:{$blocked->id}", 90)->acquire();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id])
        ->assertRedirect('https://secure.3gdirectpay.com/payv2.php?ID=TOKEN123');

    expect(Payment::count())->toBe(1);
});

it('releases the lock so the customer can retry after a gateway failure', function () {
    Http::fake(['secure.3gdirectpay.com/*' => Http::response(dpoResponse(
        '<Result>801</Result><ResultExplanation>Missing Fields</ResultExplanation>'
    ))]);
    $user = dpoBuyer();
    $plan = dpoPlan();

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    expect(Cache::lock("dpo:checkout:{$user->id}", 90)->get())->toBeTrue();
});
