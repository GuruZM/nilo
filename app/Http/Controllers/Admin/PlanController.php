<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Currency;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->withCount(['subscriptions' => function ($query) {
                $query->where('status', 'active');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/plans/index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/plans/create', [
            'currencies' => $this->currencyOptions(),
            'nextSortOrder' => (int) Plan::max('sort_order') + 1,
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $plan = Plan::create($this->normalize($request->validated()));

        $this->settlePopularFlag($plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', "The {$plan->name} plan has been created.");
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('admin/plans/edit', [
            'plan' => $plan,
            'currencies' => $this->currencyOptions(),
            'activeSubscriptions' => $plan->subscriptions()->where('status', 'active')->count(),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->normalize($request->validated()));

        $this->settlePopularFlag($plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', "The {$plan->name} plan has been updated.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $subscriberCount = $plan->subscriptions()->count();

        if ($subscriberCount > 0) {
            return back()->with('error', "The {$plan->name} plan still has {$subscriberCount} subscription(s) and cannot be deleted. Deactivate it instead to hide it from the pricing page.");
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('success', "The {$name} plan has been deleted.");
    }

    /**
     * Blank feature rows are an artefact of the repeater UI, not user intent.
     * The "Popular" badge only renders on the pricing page, so an unlisted plan
     * cannot hold it — otherwise it would silently strip the badge from the
     * plan that visitors actually see.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['features'] = collect($data['features'] ?? [])
            ->map(fn (?string $feature): string => trim((string) $feature))
            ->filter()
            ->values()
            ->all();

        if (array_key_exists('is_public', $data) && ! $data['is_public']) {
            $data['is_popular'] = false;
        }

        return $data;
    }

    /**
     * Only one plan can wear the "Popular" badge on the pricing page.
     */
    private function settlePopularFlag(Plan $plan): void
    {
        if (! $plan->is_popular) {
            return;
        }

        Plan::query()
            ->whereKeyNot($plan->getKey())
            ->where('is_popular', true)
            ->update(['is_popular' => false]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Currency>
     */
    private function currencyOptions(): \Illuminate\Support\Collection
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);
    }
}
