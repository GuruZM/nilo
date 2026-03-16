<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
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

            'auth' => [
                'user' => $request->user(),
            ],

            // ✅ Flash messages (for global toasts)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'info' => fn () => $request->session()->get('info'),
                'error' => fn () => $request->session()->get('error'),
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
                    ->get(['companies.id', 'companies.name']);

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
                ];
            },

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
