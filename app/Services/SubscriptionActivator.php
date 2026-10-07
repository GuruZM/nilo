<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The one place a subscription starts or stops because of a payment.
 *
 * Before DPO this logic lived inside the admin confirm/reject actions, where a
 * human clicking a button was the only way in. A gateway callback is a GET the
 * customer can refresh, so the same steps now have to be safe to repeat.
 */
class SubscriptionActivator
{
    public function __construct(private CouponService $coupons) {}

    /**
     * Settle a payment and start the subscription it paid for.
     *
     * Returns false when the payment was already settled — a replayed callback
     * or a reconciler racing the browser. Without that guard every refresh of
     * the return URL would push ends_at another period into the future, which
     * is a renewal for the price of a keystroke.
     */
    public function activate(Payment $payment, ?User $confirmedBy = null, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($payment, $confirmedBy, $notes): bool {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->isConfirmed()) {
                return false;
            }

            $start = now();

            $locked->update([
                'status' => 'confirmed',
                'admin_notes' => $notes ?? $locked->admin_notes,
                'confirmed_by' => $confirmedBy?->id,
                'confirmed_at' => $start,
            ]);

            $subscription = $locked->subscription;

            if ($subscription !== null) {
                $subscription->update([
                    'status' => 'active',
                    'starts_at' => $start,
                    'ends_at' => $locked->plan->periodEndFrom($start),
                ]);

                $this->supersede($subscription, $start);
            }

            $payment->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Turn a payment down and cancel whatever it was going to pay for.
     *
     * The customer never paid, so they should not have burnt their coupon.
     */
    public function reject(Payment $payment, ?User $confirmedBy = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($payment, $confirmedBy, $notes): void {
            $payment->update([
                'status' => 'rejected',
                'admin_notes' => $notes ?? $payment->admin_notes,
                'confirmed_by' => $confirmedBy?->id,
                'confirmed_at' => now(),
            ]);

            $payment->subscription?->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $this->coupons->release($payment);
        });
    }

    /**
     * Start a subscription a coupon covered in full, filing a zero-value
     * payment so the redemption still shows up in the admin ledger.
     */
    public function activateFree(User $user, Plan $plan, Coupon $coupon, float $discount): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $coupon, $discount): Subscription {
            $start = now();

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $start,
                'ends_at' => $plan->periodEndFrom($start),
                'payment_method' => 'coupon',
                'payment_reference' => $coupon->code,
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'amount' => 0,
                'original_amount' => $plan->price,
                'discount_amount' => $discount,
                'currency_code' => $plan->currency_code,
                'payment_method' => 'coupon',
                'payment_reference' => $coupon->code,
                'status' => 'confirmed',
                'confirmed_at' => $start,
            ]);

            $this->coupons->claim($coupon, $user, $plan, $payment);

            $this->supersede($subscription, $start);

            return $subscription;
        });
    }

    /**
     * Close out whatever plan the newly started one replaces.
     *
     * An upgrade bought while a plan was still running leaves two active rows
     * behind — the one still being served and the one just paid for. The paid
     * one wins, and the other is cancelled here so User::activeSubscription()
     * never has to choose between them. A paused row goes the same way: paying
     * again is how a paused customer comes back.
     */
    private function supersede(Subscription $subscription, CarbonInterface $at): void
    {
        Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->whereKeyNot($subscription->getKey())
            ->whereIn('status', ['active', 'paused'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => $at,
            ]);
    }
}
