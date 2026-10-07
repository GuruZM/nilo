<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function select(): \Inertia\Response
    {
        $plans = Plan::query()->publiclyAvailable()->get();

        return Inertia::render('subscription/select', [
            'plans' => $plans,
            // The plan they hold, not the one they may be part-way through
            // buying — the card for it offers a renewal rather than a purchase.
            'currentPlan' => auth()->user()->activePlan(),
        ]);
    }

    public function subscribe(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = $request->user();

        abort_unless($plan->is_public, 403, 'This plan is not available for sign-up.');

        $activePlan = $user->activePlan();

        // Moving down to Free is not an upgrade and cannot be self-served: the
        // account may hold more companies and documents than the free tier
        // allows, and deciding what happens to them is not this button's job.
        if ($activePlan !== null && $plan->isFreeTier()) {
            return back()->with(
                'error',
                "You are on the {$activePlan->name} plan. Contact support if you need to move down to Free."
            );
        }

        if ($plan->isEnterprise()) {
            return redirect()->route('subscription.enterprise');
        }

        if (! $plan->isFreeTier()) {
            return redirect()->route('subscription.payment', $plan);
        }

        // Free tier — create subscription immediately
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
            'payment_method' => 'free',
        ]);

        return redirect()->route('dashboard')->with('success', 'Welcome! You are now on the Free plan.');
    }

    public function current(): \Inertia\Response
    {
        $user = auth()->user();
        $limiter = new SubscriptionLimitService($user);
        $plans = Plan::query()->publiclyAvailable()->get();

        // A paused plan grants nothing, but its owner still needs to see what
        // was paused and when it fell due, not a bare "No active plan".
        $subscription = $user->activeSubscription
            ?? $user->subscriptions()->where('status', 'paused')->latest('id')->first();

        return Inertia::render('subscription/current', [
            'subscription' => $subscription?->load('plan'),
            'pendingSubscription' => $user->pendingSubscription?->load('plan'),
            'usage' => $limiter->usage(),
            'plans' => $plans,
            'payments' => $this->paymentHistory($user),
        ]);
    }

    /**
     * The subscriber's own receipts.
     *
     * Projected column by column rather than handed over whole: a Payment also
     * carries the admin's private notes and a live gateway token, and $hidden
     * only covers the gateway response blob.
     */
    private function paymentHistory(User $user): LengthAwarePaginator
    {
        return $user->payments()
            ->with('plan:id,name,slug')
            ->latest('id')
            ->paginate(10, [
                'id', 'plan_id', 'amount', 'currency_code', 'charged_amount',
                'charged_currency_code', 'payment_method', 'payment_reference',
                'coupon_code', 'discount_amount', 'status', 'created_at',
                'confirmed_at', 'paid_at',
            ])
            ->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency_code' => $payment->currency_code,
                'charged_amount' => $payment->charged_amount,
                'charged_currency_code' => $payment->charged_currency_code,
                'payment_method' => $payment->payment_method,
                'payment_reference' => $payment->payment_reference,
                'coupon_code' => $payment->coupon_code,
                'discount_amount' => $payment->discount_amount,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->toIso8601String(),
                'paid_at' => ($payment->paid_at ?? $payment->confirmed_at)?->toIso8601String(),
                'plan' => $payment->plan ? [
                    'id' => $payment->plan->id,
                    'name' => $payment->plan->name,
                ] : null,
            ]);
    }
}
