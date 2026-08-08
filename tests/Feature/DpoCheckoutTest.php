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

it('creates a pending payment and hands the browser to DPO', function () {
    fakeCreateToken();
    $plan = dpoPlan();

    $response = $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    $response->assertRedirect('https://secure.3gdirectpay.com/payv2.php?ID=TOKEN123');

    $this->assertDatabaseHas('payments', [
        'plan_id' => $plan->id,
        'payment_method' => 'dpo',
        'status' => 'pending',
        'gateway_status' => 'pending',
        'dpo_transaction_token' => 'TOKEN123',
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'plan_id' => $plan->id,
        'status' => 'pending_payment',
        'payment_reference' => 'REF123',
    ]);

    expect(Payment::first()->company_ref)->not->toBeEmpty();
});

it('does not disturb the plan a subscriber already holds', function () {
    fakeCreateToken();
    $user = activeSubscriber();
    $premium = dpoPlan(['slug' => 'premium', 'name' => 'Premium']);

    $this->actingAs($user)->post('/subscription/payment/dpo', ['plan_id' => $premium->id]);

    expect($user->fresh()->hasActiveSubscription())->toBeTrue()
        ->and($user->fresh()->activePlan()->slug)->toBe('standard');

    $this->assertDatabaseHas('subscriptions', [
        'plan_id' => $premium->id,
        'status' => 'pending_payment',
    ]);
});

/**
 * The checkout button is an Inertia XHR, which swallows a plain redirect. Only
 * a 409 with X-Inertia-Location becomes a real browser navigation.
 */
it('answers an inertia visit with a location redirect', function () {
    fakeCreateToken();
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->withHeader('X-Inertia', 'true')
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://secure.3gdirectpay.com/payv2.php?ID=TOKEN123');
});

it('sends DPO callback urls built from the configured app url', function () {
    fakeCreateToken();
    config(['app.url' => 'https://nilo.test', 'services.dpo.callback_base' => null]);
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    Http::assertSent(fn ($request) => str_contains(
        $request->body(),
        '<RedirectURL>https://nilo.test/subscription/payment/dpo/return</RedirectURL>'
    ));
});

it('prefers an explicit callback base over the app url', function () {
    fakeCreateToken();
    config(['app.url' => 'http://localhost', 'services.dpo.callback_base' => 'https://tunnel.test']);
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    Http::assertSent(fn ($request) => str_contains(
        $request->body(),
        '<BackURL>https://tunnel.test/subscription/payment/dpo/cancel</BackURL>'
    ));
});

/**
 * Nilo sells into one market, so the gateway is told the country rather than
 * the customer being made to pick it off a list mid-payment. DefaultPayment
 * chooses the tab the page opens on and DefaultPaymentCountry pre-selects the
 * country inside it; customerCountry is a plain ISO code on the record.
 */
it('tells DPO the country so the customer does not have to pick one', function () {
    fakeCreateToken();
    config([
        'services.dpo.customer_country' => 'ZM',
        'services.dpo.default_payment' => 'MO',
        'services.dpo.default_payment_country' => 'Zambia',
    ]);
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<customerCountry>ZM</customerCountry>')
        && str_contains($request->body(), '<DefaultPayment>MO</DefaultPayment>')
        && str_contains($request->body(), '<DefaultPaymentCountry>Zambia</DefaultPaymentCountry>'));
});

it('leaves the country fields out entirely when they are not configured', function () {
    fakeCreateToken();
    config([
        'services.dpo.customer_country' => null,
        'services.dpo.default_payment' => null,
        'services.dpo.default_payment_country' => null,
    ]);
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    Http::assertSent(fn ($request) => ! str_contains($request->body(), 'customerCountry')
        && ! str_contains($request->body(), 'DefaultPayment'));
});

it('unwinds the checkout when DPO refuses the transaction', function () {
    Http::fake(['secure.3gdirectpay.com/*' => Http::response(dpoResponse(
        '<Result>801</Result><ResultExplanation>Missing Fields</ResultExplanation>'
    ))]);

    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(20)->create();

    $this->actingAs(dpoBuyer())
        ->from('/subscription/payment/'.$plan->id)
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id, 'coupon_code' => $coupon->code])
        ->assertRedirect('/subscription/payment/'.$plan->id)
        ->assertSessionHas('error');

    $payment = Payment::first();

    expect($payment->status)->toBe('rejected')
        ->and($payment->gateway_status)->toBe('failed')
        ->and($payment->subscription->status)->toBe('cancelled')
        ->and(CouponRedemption::count())->toBe(0)
        ->and($coupon->fresh()->redemptions_count)->toBe(0);
});

/**
 * A gateway that answered and declined is not a gateway that is down. Telling
 * the customer to "try again" in that case sends them round a loop whose answer
 * will not change — a declined amount stays declined.
 */
it('tells a declined customer to pay another way rather than to try again', function () {
    Http::fake(['secure.3gdirectpay.com/*' => Http::response(dpoResponse(
        '<Result>904</Result><ResultExplanation>The transaction amount has exceeded your allowed transaction limit</ResultExplanation>'
    ))]);

    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->from('/subscription/payment/'.$plan->id)
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id])
        ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'turned down'));
});

it('keeps the try-again wording when the gateway never answered', function () {
    Http::fake(['secure.3gdirectpay.com/*' => Http::response('<html>403 Forbidden</html>', 403)]);

    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->from('/subscription/payment/'.$plan->id)
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id])
        ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'could not reach'));
});

/**
 * DPO's WAF answers a request carrying a callback it cannot reach with a
 * CloudFront 403 — HTML where the XML belongs. The customer must be told, and
 * the log must name the host, or the pay button just reads as dead.
 */
it('tells the customer and names the callback host when DPO turns the request away', function () {
    Http::fake(['secure.3gdirectpay.com/*' => Http::response(
        '<!DOCTYPE HTML><HTML><HEAD><TITLE>ERROR: The request could not be satisfied</TITLE></HEAD></HTML>',
        403
    )]);
    Log::spy();
    config(['services.dpo.callback_base' => 'http://localhost:8000']);

    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->from('/subscription/payment/'.$plan->id)
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id])
        ->assertRedirect('/subscription/payment/'.$plan->id)
        ->assertSessionHas('error');

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message, array $context): bool => $message === 'DPO createToken failed'
            && $context['callback_base'] === 'http://localhost:8000'
            && str_contains((string) $context['hint'], 'DPO_CALLBACK_BASE')
    );
});

/**
 * Only loopback earns the hint. A development host such as .test clears the
 * WAF and still resolves in the browser DPO redirects, so blaming it for an
 * unrelated failure would send the reader off after the wrong thing.
 */
it('leaves the diagnostic hint off for any host the gateway accepts', function (string $base) {
    Http::fake(['secure.3gdirectpay.com/*' => Http::response(dpoResponse(
        '<Result>801</Result><ResultExplanation>Missing Fields</ResultExplanation>'
    ))]);
    Log::spy();
    config(['services.dpo.callback_base' => $base]);

    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())->post('/subscription/payment/dpo', ['plan_id' => $plan->id]);

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message, array $context): bool => $message === 'DPO createToken failed'
            && $context['hint'] === null
    );
})->with(['https://app.example.com', 'https://nilo-web.test']);

it('activates the subscription when DPO confirms the payment', function () {
    $user = dpoBuyer();
    $plan = dpoPlan();
    $payment = pendingDpoPayment($user, $plan);
    fakeVerify('000', 'Transaction Paid');

    $this->actingAs($user)
        ->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123')
        ->assertRedirect('/subscription/payment-status');

    $payment->refresh();

    expect($payment->status)->toBe('confirmed')
        ->and($payment->gateway_status)->toBe('paid')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->verified_at)->not->toBeNull()
        ->and($payment->subscription->status)->toBe('active')
        ->and($payment->subscription->ends_at->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('ends a yearly subscription a year out', function () {
    $user = dpoBuyer();
    $plan = dpoPlan(['slug' => 'annual', 'billing_period' => 'yearly']);
    $payment = pendingDpoPayment($user, $plan);
    fakeVerify('000');

    $this->actingAs($user)->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123');

    expect($payment->fresh()->subscription->ends_at->toDateString())
        ->toBe(now()->addYear()->toDateString());
});

/**
 * The return url is a GET the customer can refresh. Without the lock-and-bail
 * in SubscriptionActivator, every refresh would buy another billing period.
 */
it('does not extend the subscription when the return url is replayed', function () {
    $user = dpoBuyer();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(20)->create();
    $payment = pendingDpoPayment($user, $plan, $coupon);
    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'payment_id' => $payment->id,
        'discount_amount' => 20000,
        'currency_code' => 'ZMW',
    ]);
    fakeVerify('000');

    $this->actingAs($user)->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123');

    $first = $payment->fresh()->subscription;
    $confirmedAt = $payment->fresh()->confirmed_at;

    $this->travel(5)->minutes();
    $this->actingAs($user)->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123');

    $payment->refresh();

    expect($payment->subscription->ends_at->toIso8601String())->toBe($first->ends_at->toIso8601String())
        ->and($payment->confirmed_at->toIso8601String())->toBe($confirmedAt->toIso8601String())
        ->and(CouponRedemption::count())->toBe(1);
});

it('rejects the payment and hands the coupon back when DPO declines', function () {
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
    fakeVerify('901', 'Declined');

    $this->actingAs($user)->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123');

    $payment->refresh();

    expect($payment->status)->toBe('rejected')
        ->and($payment->gateway_status)->toBe('failed')
        ->and($payment->subscription->status)->toBe('cancelled')
        ->and($coupon->fresh()->redemptions_count)->toBe(0)
        ->and(CouponRedemption::count())->toBe(0);
});

it('leaves a still-processing payment alone for the reconciler', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());
    fakeVerify('900', 'Not paid yet');

    $this->actingAs($user)->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123');

    $payment->refresh();

    expect($payment->status)->toBe('pending')
        ->and($payment->gateway_status)->toBe('pending')
        ->and($payment->subscription->status)->toBe('pending_payment');
});

it('cancels the payment when the customer backs out', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());
    fakeVerify('904', 'Cancelled');

    $this->actingAs($user)
        ->get('/subscription/payment/dpo/cancel?TransactionToken=TOKEN123')
        ->assertRedirect('/subscription/payment-status');

    $payment->refresh();

    expect($payment->status)->toBe('rejected')
        ->and($payment->gateway_status)->toBe('cancelled')
        ->and($payment->subscription->status)->toBe('cancelled');
});

/**
 * The improvement on the reference implementation, which trusts BackURL
 * blindly. A customer who pays and then presses Back must not be stranded.
 */
it('activates rather than cancels when the back url arrives after payment', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());
    fakeVerify('000', 'Transaction Paid');

    $this->actingAs($user)->get('/subscription/payment/dpo/cancel?TransactionToken=TOKEN123');

    $payment->refresh();

    expect($payment->status)->toBe('confirmed')
        ->and($payment->subscription->status)->toBe('active');
});

it('treats a second cancel as a no-op', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());
    fakeVerify('904');

    $this->actingAs($user)->get('/subscription/payment/dpo/cancel?TransactionToken=TOKEN123');
    $confirmedAt = $payment->fresh()->confirmed_at;

    $this->travel(5)->minutes();
    $this->actingAs($user)->get('/subscription/payment/dpo/cancel?TransactionToken=TOKEN123');

    expect($payment->fresh()->confirmed_at->toIso8601String())->toBe($confirmedAt->toIso8601String());
});

/**
 * DPO holds a transaction for 24 hours against a 2 hour session, so the
 * callback must work for a customer whose cookie expired mid-payment.
 */
it('settles the payment even when the session did not survive the round trip', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());
    fakeVerify('000');

    $this->get('/subscription/payment/dpo/return?TransactionToken=TOKEN123')
        ->assertRedirect('/login');

    $payment->refresh();

    expect($payment->status)->toBe('confirmed')
        ->and($payment->subscription->status)->toBe('active');
});

it('404s an unknown transaction token without touching anything', function () {
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());

    $this->get('/subscription/payment/dpo/return?TransactionToken=NOPE')->assertNotFound();

    expect($payment->fresh()->status)->toBe('pending');
    Http::assertNothingSent();
});

it('charges DPO the discounted total when a coupon applies', function () {
    fakeCreateToken();
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(20)->create();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id, 'coupon_code' => $coupon->code]);

    $payment = Payment::first();

    expect((float) $payment->amount)->toBe(80000.0)
        ->and((float) $payment->original_amount)->toBe(100000.0)
        ->and((float) $payment->discount_amount)->toBe(20000.0);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<PaymentAmount>80000.00</PaymentAmount>'));
});

it('activates straight away when a coupon covers the whole price', function () {
    $plan = dpoPlan();
    $coupon = Coupon::factory()->percentage(100)->create();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id, 'coupon_code' => $coupon->code])
        ->assertRedirect('/dashboard');

    expect(Payment::first()->payment_method)->toBe('coupon');
    Http::assertNothingSent();
});

it('hides the gateway entirely when it is switched off', function () {
    config(['services.dpo.enabled' => false]);

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => dpoPlan()->id])
        ->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('refuses to sell a plan that is not public', function () {
    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => dpoPlan(['slug' => 'hidden', 'is_public' => false])->id])
        ->assertForbidden();
});

it('will not resume another subscriber\'s payment', function () {
    $payment = pendingDpoPayment(dpoBuyer(), dpoPlan());

    $this->actingAs(dpoBuyer())
        ->post("/subscription/payment/dpo/{$payment->id}/resume")
        ->assertForbidden();
});

it('sends a subscriber back to the transaction DPO already holds', function () {
    $user = dpoBuyer();
    $payment = pendingDpoPayment($user, dpoPlan());

    $this->actingAs($user)
        ->post("/subscription/payment/dpo/{$payment->id}/resume")
        ->assertRedirect('https://secure.3gdirectpay.com/payv2.php?ID=TOKEN123');

    Http::assertNothingSent();
});
