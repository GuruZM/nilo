<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Http\Resources\Api\V1\CurrencyResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Currency;
use App\Services\CurrencyRollup;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the app needs to draw its shell.
 *
 * The web client gets this free on every page load through
 * {@see \App\Http\Middleware\HandleInertiaRequests::share()}; a mobile client
 * has no equivalent, so the same context is served here as one call. The shape
 * deliberately mirrors those shared props so the two clients agree.
 *
 * Deliberately outside the company and subscription gates: the app has to be
 * able to fetch this before it knows which company to name, and while it still
 * has no plan.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $companies = $user->companies()->orderBy('name')->get();

        $subscription = $user->activeSubscription;
        $plan = $subscription?->plan;
        $limiter = new SubscriptionLimitService($user);

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'companies' => [
                'all' => CompanyResource::collection($companies),
                'current_id' => $this->currentCompanyId($user, $companies),
            ],
            'currencies' => [
                'all' => CurrencyResource::collection(
                    Currency::query()->where('is_active', true)->orderBy('code')->get()
                ),
                'current' => $user->displayCurrencyCode(),
                'rates_as_of' => CurrencyRollup::ratesAsOf()?->toIso8601String(),
            ],
            'subscription' => [
                'plan' => $plan ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                ] : null,
                'status' => $subscription?->status,
                'usage' => $limiter->usage(),
            ],
        ]);
    }

    /**
     * The company the app should start on.
     *
     * Reported rather than enforced: from here on the client names its own
     * company on every request, so nothing is written back to the user.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Company>  $companies
     */
    private function currentCompanyId(\App\Models\User $user, $companies): ?int
    {
        $stored = (int) ($user->current_company_id ?? 0);

        if ($stored && $companies->contains('id', $stored)) {
            return $stored;
        }

        return $companies->first()?->id;
    }
}
