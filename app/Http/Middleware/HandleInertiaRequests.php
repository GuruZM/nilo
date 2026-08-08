<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use App\Services\CurrencyRollup;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            /**
             * Keeps the `csrf-token` meta tag current.
             *
             * The tag is rendered once per document, but Inertia swaps pages
             * without touching `<head>`, so it goes stale the moment the token
             * is regenerated — on logout, or when a session expires. Hand-rolled
             * `fetch` calls read the tag and then fail with a 419, while Inertia's
             * own requests carry on working off the XSRF cookie. Marked `always`
             * so partial reloads carry it too.
             */
            'csrfToken' => Inertia::always(csrf_token()),

            'auth' => [
                'user' => $request->user(),
                'roles' => function () use ($request) {
                    if (! $request->user() || ! Schema::hasTable('roles')) {
                        return [];
                    }

                    return $request->user()->getRoleNames();
                },
            ],

            // ✅ Flash messages (for global toasts)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'info' => fn () => $request->session()->get('info'),
                'error' => fn () => $request->session()->get('error'),

                /**
                 * Set when a client or supplier is created from inside a
                 * half-built document. The builder redirects back to itself, so
                 * these ids are the only way the picker can tell which of the
                 * refreshed records is the new one and select it.
                 */
                'created_client_id' => fn () => $request->session()->get('created_client_id'),
                'created_supplier_id' => fn () => $request->session()->get('created_supplier_id'),
            ],

            // ✅ Company context (available on every page)
            'companies' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return [
                        'all' => [],
                        'current' => null,
                    ];
                }

                $all = $user->companies()
                    ->orderBy('name')
                    ->get(['companies.id', 'companies.name', 'companies.type', 'companies.currency_code']);

                $currentId = $user->current_company_id;

                // If none set, fall back to the first company and persist once
                if (! $currentId && $all->first()) {
                    $currentId = $all->first()->id;
                    $user->forceFill(['current_company_id' => $currentId])->save();
                }

                $current = $currentId
                    ? $all->firstWhere('id', (int) $currentId)
                    : null;

                return [
                    'all' => $all,
                    'current' => $current,
                ];
            },

            // ✅ Currency context (available on every page)
            'currencies' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return [
                        'all' => [],
                        'current' => null,
                    ];
                }

                // Only active currencies should show in switcher
                $all = Currency::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['code', 'name', 'symbol', 'precision']);

                $currentCode = $user->current_currency_code;

                // If none set, fall back to ZMW (if available) else first active and persist once
                if (! $currentCode) {
                    $fallback = $all->firstWhere('code', 'ZMW') ?? $all->first();

                    if ($fallback) {
                        $currentCode = $fallback->code;
                        $user->forceFill(['current_currency_code' => $currentCode])->save();
                    }
                }

                $current = $currentCode
                    ? $all->firstWhere('code', strtoupper((string) $currentCode))
                    : null;

                return [
                    'all' => $all,
                    'current' => $current,
                    /** Lets converted figures say which day's rates they used. */
                    'rates_as_of' => CurrencyRollup::ratesAsOf()?->toIso8601String(),
                ];
            },

            'subscription' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return null;
                }

                $subscription = $user->activeSubscription;
                $plan = $subscription?->plan;
                $limiter = new SubscriptionLimitService($user);

                return [
                    'plan' => $plan ? [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                    ] : null,
                    'status' => $subscription?->status,
                    'usage' => $limiter->usage(),
                ];
            },

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',

            // ✅ Which social login providers are enabled (drives the auth buttons)
            'oauth' => [
                'linkedin' => (bool) config('services.linkedin-openid.enabled'),
            ],
        ];
    }
}
