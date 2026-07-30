<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Prices a plan purchase and hands out coupon claims.
 *
 * Every total the customer is shown comes from {@see quote()}, and the purchase
 * endpoints re-derive it server-side rather than trusting a posted amount.
 */
class CouponService
{
    /**
     * What the customer owes for this plan, with the code applied if it holds
     * up. An unusable code never blocks the page — it comes back as `error`
     * with the undiscounted total, so the purchase can still go ahead.
     *
     * @return array{subtotal: float, discount: float, total: float, currency_code: string, coupon: array{code: string, label: string, description: string|null}|null, error: string|null}
     */
    public function quote(Plan $plan, User $user, ?string $code): array
    {
        $subtotal = round((float) $plan->price, 2);

        $quote = [
            'subtotal' => $subtotal,
            'discount' => 0.0,
            'total' => $subtotal,
            'currency_code' => $plan->currency_code,
            'coupon' => null,
            'error' => null,
        ];

        $code = trim((string) $code);

        if ($code === '') {
            return $quote;
        }

        $coupon = $this->lookup($code);
        $rejection = $this->rejectionReason($coupon, $plan, $user);

        if ($rejection !== null) {
            $quote['error'] = $rejection;

            return $quote;
        }

        $discount = $coupon->discountFor($plan);

        return [
            ...$quote,
            'discount' => $discount,
            'total' => round($subtotal - $discount, 2),
            'coupon' => [
                'code' => $coupon->code,
                'label' => $coupon->label(),
                'description' => $coupon->description,
            ],
        ];
    }

    /**
     * The coupon behind a code, or a validation error the purchase forms can
     * surface on their coupon field.
     *
     * @throws ValidationException
     */
    public function findRedeemable(Plan $plan, User $user, string $code): Coupon
    {
        $coupon = $this->lookup($code);
        $rejection = $this->rejectionReason($coupon, $plan, $user);

        if ($rejection !== null) {
            throw ValidationException::withMessages(['coupon_code' => $rejection]);
        }

        return $coupon;
    }

    /**
     * Take one off the coupon's remaining stock and record who took it.
     *
     * The count moves in a single conditional UPDATE, so two customers racing
     * for the last redemption cannot both win it — the loser is told the
     * coupon just ran out rather than silently getting the discount.
     *
     * @throws ValidationException
     */
    public function claim(Coupon $coupon, User $user, Plan $plan, Payment $payment): CouponRedemption
    {
        $claimed = Coupon::query()
            ->whereKey($coupon->getKey())
            ->where(function ($query) {
                $query->whereNull('max_redemptions')
                    ->orWhereColumn('redemptions_count', '<', 'max_redemptions');
            })
            ->increment('redemptions_count');

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon has just reached its redemption limit.',
            ]);
        }

        return CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'payment_id' => $payment->id,
            'discount_amount' => $payment->discount_amount,
            'currency_code' => $payment->currency_code,
        ]);
    }

    /**
     * Hand the claim back when a payment is rejected, so a customer whose
     * transfer never landed does not burn a single-use code.
     */
    public function release(Payment $payment): void
    {
        $redemption = $payment->redemption()->first();

        if ($redemption === null) {
            return;
        }

        Coupon::query()
            ->whereKey($redemption->coupon_id)
            ->where('redemptions_count', '>', 0)
            ->decrement('redemptions_count');

        $redemption->delete();
    }

    private function lookup(string $code): ?Coupon
    {
        return Coupon::query()->code($code)->with('plans')->first();
    }

    private function rejectionReason(?Coupon $coupon, Plan $plan, User $user): ?string
    {
        return match (true) {
            $coupon === null => 'That coupon code is not valid.',
            ! $coupon->is_active => 'That coupon is no longer available.',
            ! $coupon->hasStarted() => 'That coupon is not active yet.',
            $coupon->hasExpired() => 'That coupon has expired.',
            ! $coupon->hasRedemptionsLeft() => 'That coupon has reached its redemption limit.',
            ! $coupon->appliesToPlan($plan) => "That coupon does not apply to the {$plan->name} plan.",
            $coupon->once_per_user && $coupon->wasRedeemedBy($user) => 'You have already used that coupon.',
            default => null,
        };
    }
}
