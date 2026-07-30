<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function select(): \Inertia\Response
    {
        $plans = Plan::query()->publiclyAvailable()->get();

        $user = auth()->user();
        $currentSubscription = $user->subscription;

        return Inertia::render('subscription/select', [
            'plans' => $plans,
            'currentPlan' => $currentSubscription?->plan,
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

        return Inertia::render('subscription/current', [
            'subscription' => $user->subscription?->load('plan'),
            'usage' => $limiter->usage(),
            'plans' => $plans,
        ]);
    }
}
