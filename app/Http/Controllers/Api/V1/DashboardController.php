<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceListResource;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Services\CurrencyRollup;
use App\Services\OutstandingBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The headline figures a phone opens on.
 *
 * Deliberately narrower than the web dashboard: the twelve-month trend and the
 * top-clients table are screen-sized things, and a mobile client that wants
 * them can page the invoice list. What is here is what fits above the fold —
 * what is owed, what is late, and what lands this week.
 *
 * Every figure is converted into the user's display currency using the shared
 * rollup, so a company invoicing in several currencies still gets one number
 * rather than a pile of incomparable ones.
 */
class DashboardController extends Controller
{
    use ResolvesCompany;

    public function __construct(private OutstandingBalance $balances) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();

        $displayCode = $user->displayCurrencyCode();
        $rollup = CurrencyRollup::into($displayCode, $companyId);

        $today = Carbon::today();
        $inSevenDays = $today->copy()->addDays(7);

        $base = Invoice::query()->where('company_id', $companyId);

        $overdue = (clone $base)
            ->outstanding()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);

        $dueSoon = (clone $base)
            ->outstanding()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $inSevenDays);

        return response()->json([
            'currency' => [
                'code' => $displayCode,
                'precision' => (int) (Currency::query()->where('code', $displayCode)->value('precision') ?? 2),
                'rates_as_of' => CurrencyRollup::ratesAsOf()?->toIso8601String(),
            ],
            'counts' => [
                'invoices' => (clone $base)->count(),
                'paid' => (clone $base)->paid()->count(),
                'outstanding' => (clone $base)->outstanding()->count(),
                'overdue' => (clone $overdue)->count(),
                'clients' => Client::query()->where('company_id', $companyId)->count(),
            ],
            'money' => [
                /** Revenue collected: what was billed on invoices now settled. */
                'paid' => $rollup->sum((clone $base)->paid()),

                /** Every outstanding figure is net of what has already been settled. */
                'outstanding' => $this->balances->forQuery((clone $base)->outstanding(), $rollup),
                'overdue' => $this->balances->forQuery(clone $overdue, $rollup),
                'due_in_7_days' => $this->balances->forQuery(clone $dueSoon, $rollup),
            ],
            'recent_invoices' => InvoiceListResource::collection(
                (clone $base)->with('client:id,company_id,name')->orderByDesc('created_at')->limit(5)->get()
            ),
        ]);
    }
}
