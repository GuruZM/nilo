<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StartsDpoCheckout;
use App\Http\Requests\StartDpoCheckoutRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CouponService;
use App\Services\Dpo\Data\DpoVerifyTokenResult;
use App\Services\Dpo\DpoCharge;
use App\Services\Dpo\DpoClient;
use App\Services\Dpo\DpoException;
use App\Services\SubscriptionActivator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checkout through DPO's hosted payment page.
 *
 * The two callbacks sit outside `auth` on purpose: DPO's page holds a
 * transaction for up to 24 hours against a 2 hour session, and some mobile
 * money flows finish on another device entirely. A customer who paid must end
 * up subscribed whether or not their cookie survived the trip. Nothing is read
 * off the query string — the outcome always comes from a fresh verifyToken
 * authenticated with our own company token.
 */
class DpoPaymentController extends Controller
{
    use StartsDpoCheckout;

    public function __construct(
        private CouponService $coupons,
        private SubscriptionActivator $activator,
        private DpoCharge $charge,
    ) {}

    public function checkout(StartDpoCheckoutRequest $request, DpoClient $client): RedirectResponse|Response
    {
        abort_unless((bool) config('services.dpo.enabled'), 404);

        $plan = Plan::findOrFail($request->integer('plan_id'));
        $user = $request->user();

        abort_unless($plan->is_public, 403, 'This plan cannot be purchased.');
        abort_if($plan->isFreeTier(), 403, 'The Free plan does not require a payment.');

        // Everything past this point either mints a live transaction at DPO or
        // spends a coupon, and a customer who double-clicks must not get two of
        // either. Held for longer than the gateway's own timeout so the lock
        // cannot lapse while createToken is still in flight.
        $lock = Cache::lock("dpo:checkout:{$user->id}", 90);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            return back()->with('info', 'Your payment is already being set up. Give it a moment, then check your payment status.');
        }

        try {
            return $this->startCheckout($request, $client, $plan, $user);
        } finally {
            $lock->release();
        }
    }

    /**
     * The body of a checkout, run under the per-customer lock.
     */
    private function startCheckout(StartDpoCheckoutRequest $request, DpoClient $client, Plan $plan, User $user): RedirectResponse|Response
    {
        $code = trim((string) $request->input('coupon_code'));
        $coupon = $code === '' ? null : $this->coupons->findRedeemable($plan, $user, $code);
        $discount = $coupon?->discountFor($plan) ?? 0.0;
        $total = round((float) $plan->price - $discount, 2);

        // A zero-value transaction is not something DPO will take, and there
        // is nothing to charge for anyway.
        if ($total <= 0 && $coupon !== null) {
            $this->activator->activateFree($user, $plan, $coupon, $discount);

            return redirect()->route('dashboard')
                ->with('success', "Coupon {$coupon->code} applied — you are now on the {$plan->name} plan.");
        }

        $charge = $this->charge->resolve($total, $plan->currency_code, $request->input('currency'));

        if ($charge === null) {
            throw ValidationException::withMessages([
                'currency' => 'We cannot price this plan in that currency right now. Please pay in '.$plan->currency_code.'.',
            ]);
        }

        $live = Payment::query()->resumableGatewayCheckouts($user)->latest('id')->first();

        if ($live !== null) {
            // The same purchase asked for twice is one purchase. Handing back
            // the token DPO already holds is what makes a double-click, a
            // refresh or a retried request harmless.
            if ($live->matchesCheckout($plan, $coupon, $charge)) {
                return Inertia::location($client->paymentUrl($live->dpo_transaction_token));
            }

            $this->retire($live, $client);
        }

        $payment = DB::transaction(function () use ($user, $plan, $coupon, $discount, $total, $charge): Payment {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending_payment',
                'starts_at' => now(),
                'payment_method' => Payment::METHOD_DPO,
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
                'charged_amount' => $charge['amount'],
                'charged_currency_code' => $charge['currency'],
                'charged_exchange_rate' => $charge['rate'],
                'charged_rate_fetched_at' => $charge['as_of'],
                'payment_method' => Payment::METHOD_DPO,
                'company_ref' => (string) Str::ulid(),
                'status' => 'pending',
                'gateway_status' => 'pending',
            ]);

            // Claimed now rather than on settlement: a capped coupon must not
            // be handed out twice while the first customer is still at DPO.
            if ($coupon !== null) {
                $this->coupons->claim($coupon, $user, $plan, $payment);
            }

            return $payment;
        });

        return $this->startDpoCheckout($payment->load(['user', 'plan', 'subscription']), $client, $this->coupons);
    }

    /**
     * DPO's RedirectURL, hit after the customer completes or abandons payment.
     */
    public function return(Request $request, DpoClient $client): RedirectResponse
    {
        $payment = $this->paymentForToken($request);
        $result = $client->verifyToken($payment->dpo_transaction_token);

        $this->settle($payment, $result);

        return $this->backToStatus($request, $payment, match ($result->status) {
            'paid' => ['success', 'Payment received — your subscription is active.'],
            'pending' => ['info', 'Your payment is still being processed. We will activate your plan as soon as it clears.'],
            default => ['error', 'Your payment was not completed. You can try again below.'],
        });
    }

    /**
     * DPO's BackURL, hit when the customer backs out of the hosted page.
     *
     * Verified first rather than taken at face value: cancelling here also
     * releases the coupon and cancels the subscription, so a Back press that
     * lands after the money moved would strand a paying customer.
     */
    public function cancel(Request $request, DpoClient $client): RedirectResponse
    {
        $payment = $this->paymentForToken($request);

        if (! $payment->isPending()) {
            return $this->backToStatus($request, $payment, ['info', 'That payment has already been settled.']);
        }

        $result = $client->verifyToken($payment->dpo_transaction_token);

        if ($result->status === 'paid') {
            $this->settle($payment, $result);

            return $this->backToStatus($request, $payment, ['success', 'Payment received — your subscription is active.']);
        }

        $payment->update([
            'gateway_status' => 'cancelled',
            'gateway_response' => $result->raw,
            'verified_at' => now(),
        ]);

        $this->activator->reject($payment);

        return $this->backToStatus($request, $payment, ['info', 'Payment cancelled. You can try again whenever you are ready.']);
    }

    /**
     * Send a customer back to a transaction they walked away from, reusing the
     * token DPO already holds rather than opening a second one.
     */
    public function resume(Request $request, Payment $payment, DpoClient $client): Response
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless($payment->isGateway() && $payment->isPending() && $payment->dpo_transaction_token, 404);

        return Inertia::location($client->paymentUrl($payment->dpo_transaction_token));
    }

    /**
     * Close out a transaction the customer has moved on from.
     *
     * Left alone it stays payable at DPO for the rest of its PTL, so a customer
     * who switched plan could still be charged for the one they abandoned.
     * Cancelling at DPO is best effort — if the gateway will not take the
     * cancellation the local rows must still be closed, and the reconciler
     * settles it properly should the customer somehow pay it after all.
     */
    private function retire(Payment $payment, DpoClient $client): void
    {
        try {
            $client->cancelToken($payment->dpo_transaction_token);
        } catch (DpoException $exception) {
            Log::warning('Could not cancel a superseded DPO transaction', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $payment->update(['gateway_status' => 'cancelled']);

        $this->activator->reject($payment, null, 'Superseded by a new checkout.');
    }

    /**
     * Record what DPO said and act on it. Safe to run twice — activation is
     * guarded by its own lock, and a settled payment is never re-settled.
     */
    private function settle(Payment $payment, DpoVerifyTokenResult $result): void
    {
        $payment->update([
            'gateway_status' => $result->status,
            'gateway_response' => $result->raw,
            'verified_at' => now(),
        ]);

        if ($result->status === 'paid') {
            if ($this->activator->activate($payment)) {
                $payment->forceFill(['paid_at' => now()])->save();
            }

            return;
        }

        // A pending transaction is still live at DPO; the reconciler picks it
        // up once it has had time to resolve.
        if ($result->isTerminal() && $payment->isPending()) {
            $this->activator->reject($payment);
        }
    }

    private function paymentForToken(Request $request): Payment
    {
        $token = (string) ($request->query('TransactionToken') ?? $request->query('TransToken'));

        abort_if($token === '', 404);

        return Payment::query()
            ->where('dpo_transaction_token', $token)
            ->with(['plan', 'subscription'])
            ->firstOrFail();
    }

    /**
     * @param  array{0: string, 1: string}  $flash
     */
    private function backToStatus(Request $request, Payment $payment, array $flash): RedirectResponse
    {
        [$key, $message] = $flash;

        // The session may not have survived the round trip through DPO. The
        // payment is already settled server-side, so signing back in is all
        // that is left to do.
        if ($request->user()?->id !== $payment->user_id) {
            return redirect()->route('login')->with($key, $message.' Please sign in to continue.');
        }

        return redirect()->route('subscription.payment.status')->with($key, $message);
    }
}
