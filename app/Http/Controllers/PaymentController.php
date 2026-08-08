<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CouponService;
use App\Services\Dpo\DpoCharge;
use App\Services\SubscriptionActivator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private CouponService $coupons,
        private SubscriptionActivator $activator,
        private DpoCharge $charge,
    ) {}

    public function create(Request $request, Plan $plan): Response
    {
        abort_unless($plan->is_public, 403, 'This plan cannot be purchased.');
        abort_if($plan->isFreeTier(), 403, 'The Free plan does not require a payment.');

        $code = trim($request->string('coupon')->toString());
        $quote = $this->coupons->quote($plan, $request->user(), $code);

        $currencies = $this->charge->availableCurrencies($plan->currency_code);
        $currency = strtoupper($request->string('currency')->toString());
        $currency = in_array($currency, $currencies, true) ? $currency : $plan->currency_code;

        return Inertia::render('subscription/payment', [
            'plan' => $plan,
            'quote' => $quote,
            'couponCode' => $code,
            'dpoEnabled' => (bool) config('services.dpo.enabled'),
            'currencies' => $currencies,
            'currency' => $currency,
            'charge' => $this->charge->resolve((float) $quote['total'], $plan->currency_code, $currency),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $plan = Plan::findOrFail($request->integer('plan_id'));
        $user = $request->user();

        abort_unless($plan->is_public, 403, 'This plan cannot be purchased.');
        abort_if($plan->isFreeTier(), 403, 'The Free plan does not require a payment.');

        $coupon = $this->resolveCoupon($request, $plan, $user);
        $discount = $coupon?->discountFor($plan) ?? 0.0;
        $total = round((float) $plan->price - $discount, 2);

        // A coupon can settle the whole bill, in which case there is nothing to
        // transfer and no transfer for an admin to verify.
        if ($total <= 0 && $coupon !== null) {
            return $this->activateWithoutPayment($user, $plan, $coupon, $discount);
        }

        $popFilePath = $request->file('pop_file')?->store('payments/pop', 'public');

        DB::transaction(function () use ($request, $user, $plan, $coupon, $discount, $total, $popFilePath) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending_payment',
                'starts_at' => now(),
                'payment_method' => $request->payment_method,
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'amount' => $total,
                'original_amount' => $plan->price,
                'discount_amount' => $discount,
                'currency_code' => $plan->currency_code,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'pop_file_path' => $popFilePath,
                'status' => 'pending',
            ]);

            // Claimed now rather than on confirmation: a capped coupon must not
            // be handed out twice while the first payment waits for review.
            if ($coupon !== null) {
                $this->coupons->claim($coupon, $user, $plan, $payment);
            }
        });

        return redirect()->route('subscription.payment.status')
            ->with('success', 'Payment submitted. We will confirm your payment shortly.');
    }

    /**
     * The checkout path for a coupon that covers the plan in full.
     */
    public function redeem(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'coupon_code' => 'required|string|max:64',
        ]);

        $plan = Plan::findOrFail($request->integer('plan_id'));
        $user = $request->user();

        abort_unless($plan->is_public, 403, 'This plan cannot be purchased.');

        $coupon = $this->coupons->findRedeemable($plan, $user, $request->string('coupon_code')->toString());
        $discount = $coupon->discountFor($plan);
        $total = round((float) $plan->price - $discount, 2);

        // Guards against a stale page: if the coupon only covers part of the
        // price, send them back to settle the balance rather than gifting it.
        if ($total > 0) {
            return redirect()
                ->route('subscription.payment', ['plan' => $plan, 'coupon' => $coupon->code])
                ->with('info', 'This coupon covers part of the price. Please pay the remaining balance.');
        }

        return $this->activateWithoutPayment($user, $plan, $coupon, $discount);
    }

    public function status(): Response
    {
        $user = auth()->user();
        $latestPayment = $user->payments()->with('plan')->latest()->first();

        // The page is about that payment, so it reports the subscription that
        // payment bought rather than whichever one is currently serving.
        $subscription = $latestPayment?->subscription ?? $user->activeSubscription;

        return Inertia::render('subscription/payment-status', [
            'payment' => $latestPayment,
            'subscription' => $subscription?->load('plan'),
            // Pulled out by hand rather than exposing gateway_response, which
            // also carries card metadata the subscriber has no need to see.
            'gatewayMessage' => $latestPayment?->isGateway()
                ? ($latestPayment->gateway_response['ResultExplanation'] ?? null)
                : null,
        ]);
    }

    /**
     * Starts the subscription straight away and files a zero-value payment so
     * the redemption still shows up in the admin payment ledger.
     */
    private function activateWithoutPayment(User $user, Plan $plan, Coupon $coupon, float $discount): RedirectResponse
    {
        $this->activator->activateFree($user, $plan, $coupon, $discount);

        return redirect()->route('dashboard')
            ->with('success', "Coupon {$coupon->code} applied — you are now on the {$plan->name} plan.");
    }

    private function resolveCoupon(StorePaymentRequest $request, Plan $plan, User $user): ?Coupon
    {
        $code = trim((string) $request->input('coupon_code'));

        if ($code === '') {
            return null;
        }

        return $this->coupons->findRedeemable($plan, $user, $code);
    }
}
