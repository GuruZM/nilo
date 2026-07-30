<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = User::with(['subscription.plan'])
            ->withCount('ownedCompanies');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($planFilter = $request->input('plan')) {
            $query->whereHas('subscription.plan', function ($q) use ($planFilter) {
                $q->where('slug', $planFilter);
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'plan' => $planFilter,
            ],
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'is_public']),
        ]);
    }

    public function show(User $user): \Inertia\Response
    {
        $user->load(['subscription.plan', 'payments.plan', 'ownedCompanies']);

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * A plan change supersedes the old subscription rather than rewriting it, so
     * the billing history stays intact. Edits that keep the same plan — flipping
     * a status back to active, extending an expiry — amend the row in place.
     */
    public function updateSubscription(UpdateUserSubscriptionRequest $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();
        $plan = Plan::findOrFail($validated['plan_id']);
        $current = $user->subscription;

        $attributes = [
            'status' => $validated['status'],
            'ends_at' => $validated['ends_at'] ?? null,
            'cancelled_at' => $validated['status'] === 'cancelled' ? now() : null,
        ];

        if ($current && $current->plan_id === $plan->id) {
            $current->update($attributes);

            return back()->with('success', "The {$plan->name} subscription has been updated.");
        }

        if ($current) {
            $current->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        Subscription::create([
            ...$attributes,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'payment_method' => 'admin_assigned',
        ]);

        return back()->with('success', "{$user->name} has been moved to the {$plan->name} plan.");
    }
}
