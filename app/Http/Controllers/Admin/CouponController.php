<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        $coupons = Coupon::query()
            ->with('plans:id,name')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('admin/coupons/index', [
            'coupons' => $coupons,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/create', [
            'currencies' => $this->currencyOptions(),
            'plans' => $this->planOptions(),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $data = $this->normalize($request->validated());

        $coupon = Coupon::create($data);
        $coupon->plans()->sync($request->input('plan_ids', []));

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', "Coupon {$coupon->code} has been created.");
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/edit', [
            'coupon' => [
                ...$coupon->toArray(),
                'plan_ids' => $coupon->plans()->pluck('plans.id'),
            ],
            'currencies' => $this->currencyOptions(),
            'plans' => $this->planOptions(),
            'redemptions' => $coupon->redemptions()->count(),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->normalize($request->validated()));
        $coupon->plans()->sync($request->input('plan_ids', []));

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', "Coupon {$coupon->code} has been updated.");
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $redemptions = $coupon->redemptions()->count();

        if ($redemptions > 0) {
            return back()->with('error', "Coupon {$coupon->code} has already been redeemed {$redemptions} time(s) and cannot be deleted. Deactivate it instead so the redemption history survives.");
        }

        $code = $coupon->code;
        $coupon->delete();

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', "Coupon {$code} has been deleted.");
    }

    /**
     * A percentage coupon has no currency of its own — it inherits whatever the
     * plan is priced in — so storing one would be a lie waiting to be read.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        unset($data['plan_ids']);

        if (($data['discount_type'] ?? null) === Coupon::TYPE_PERCENTAGE) {
            $data['currency_code'] = null;
        }

        return $data;
    }

    /**
     * @return Collection<int, Currency>
     */
    private function currencyOptions(): Collection
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);
    }

    /**
     * Only purchasable plans — a coupon cannot be spent on a plan nobody can
     * reach a checkout for.
     *
     * @return Collection<int, Plan>
     */
    private function planOptions(): Collection
    {
        return Plan::query()
            ->publiclyAvailable()
            ->get(['id', 'name', 'price', 'currency_code', 'billing_period']);
    }
}
