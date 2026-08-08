<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Services\CurrencyRollup;
use App\Services\OutstandingBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private OutstandingBalance $balances) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // ✅ Use your active company context (consistent with the rest of your system)
        $companyId = (int) ($user?->current_company_id ?? 0);

        // Fallback to first company if none set
        if (! $companyId) {
            $companyId = (int) $user->companies()->value('companies.id');
            if ($companyId) {
                $user->forceFill(['current_company_id' => $companyId])->save();
            }
        }

        if (! $companyId) {
            return Inertia::render('dashboard', [
                'company' => null,
                'stats' => null,
                'charts' => null,
                'calendar' => [],
                'top_clients' => [],
                'fx' => null,
            ]);
        }

        $company = $user->companies()
            ->where('companies.id', $companyId)
            ->first(['companies.id', 'companies.name']);

        $today = Carbon::today();
        $in7 = $today->copy()->addDays(7);
        $start12 = $today->copy()->startOfMonth()->subMonths(11);

        /**
         * ✅ Overall stats (counts + money)
         * Assumes Invoice columns: total, status, due_date, issue_date, client_id
         *
         * "Outstanding" spans every unpaid status rather than `pending` alone,
         * because emailing an invoice moves it to `sent`.
         */
        $base = Invoice::query()->where('company_id', $companyId);

        $totalInvoices = (clone $base)->count();
        $paidCount = (clone $base)->paid()->count();
        $pendingCount = (clone $base)->outstanding()->count();

        /**
         * Money is recorded in whatever currency each invoice was issued in, so
         * every total below is converted into the user's display currency
         * rather than summed raw.
         */
        $displayCode = $user->displayCurrencyCode();
        $displayPrecision = (int) (Currency::query()
            ->where('code', $displayCode)
            ->value('precision') ?? 2);

        $rollup = CurrencyRollup::into($displayCode, (int) $companyId);

        $paidRevenue = $rollup->sum((clone $base)->paid());

        /** Every outstanding figure is net of what has already been settled. */
        $pendingRevenue = $this->balances->forQuery((clone $base)->outstanding(), $rollup);

        $overdueRevenue = $this->balances->forQuery((clone $base)
            ->outstanding()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today), $rollup);

        $dueIn7Revenue = $this->balances->forQuery((clone $base)
            ->outstanding()
            ->whereNotNull('due_date')
            ->whereBetween(DB::raw('DATE(due_date)'), [$today->toDateString(), $in7->toDateString()]), $rollup);

        $clientCount = (int) Client::query()
            ->where('company_id', $companyId)
            ->count();

        /**
         * ✅ Paid vs Pending chart (counts + revenue)
         * Easy for your UI to render as donut/bar
         */
        $paidVsPending = [
            'labels' => ['Paid', 'Pending'],
            'counts' => [$paidCount, $pendingCount],
            'revenue' => [$paidRevenue, $pendingRevenue],
        ];

        /**
         * ✅ Monthly trend chart (last 12 months)
         * - paid_total per month
         * - pending_total issued per month (optional but useful)
         */
        // Fetch rows for the window and bucket by month in PHP so the
        // aggregation stays portable across database drivers (Postgres + SQLite).
        $trendInvoices = (clone $base)
            ->whereDate('issue_date', '>=', $start12->toDateString())
            ->withSum('payments as paid_sum', 'amount')
            ->get();

        $paidByMonth = [];
        $pendingByMonth = [];

        foreach ($trendInvoices as $inv) {
            $ym = Carbon::parse($inv->issue_date)->format('Y-m');

            /**
             * Historical bars use the rate frozen when the invoice was issued,
             * so last month's figure does not shift when today's rate moves.
             */
            if ($inv->status === Invoice::STATUS_PAID) {
                $paidByMonth[$ym] = ($paidByMonth[$ym] ?? 0) + $rollup->convert(
                    (float) $inv->total,
                    (string) $inv->currency_code,
                    $inv->exchange_rate_to_base,
                );
            } elseif ($inv->isOutstanding()) {
                /** The bar tracks money owed, so part payments come off it. */
                $pendingByMonth[$ym] = ($pendingByMonth[$ym] ?? 0) + $rollup->convert(
                    $this->balances->unpaidRemainder($inv),
                    (string) $inv->currency_code,
                    $inv->exchange_rate_to_base,
                );
            }
        }

        // Overdue amount grouped by due month (still outstanding + past due)
        $overdueInvoices = (clone $base)
            ->outstanding()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereDate('due_date', '>=', $start12->toDateString())
            ->withSum('payments as paid_sum', 'amount')
            ->get();

        $overdueByMonth = [];

        foreach ($overdueInvoices as $inv) {
            $ym = Carbon::parse($inv->due_date)->format('Y-m');

            $overdueByMonth[$ym] = ($overdueByMonth[$ym] ?? 0) + $rollup->convert(
                $this->balances->unpaidRemainder($inv),
                (string) $inv->currency_code,
                $inv->exchange_rate_to_base,
            );
        }

        // New clients per month (turned into a cumulative running total below)
        $newClients = Client::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '>=', $start12->toDateString())
            ->get(['created_at']);

        $clientsByMonth = [];

        foreach ($newClients as $c) {
            $ym = Carbon::parse($c->created_at)->format('Y-m');
            $clientsByMonth[$ym] = ($clientsByMonth[$ym] ?? 0) + 1;
        }

        $clientsRunning = (int) Client::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '<', $start12->toDateString())
            ->count();

        // Ensure all months exist
        $months = [];
        $paidSeries = [];
        $pendingSeries = [];
        $overdueSeries = [];
        $clientsSeries = [];

        $cursor = $start12->copy();

        for ($i = 0; $i < 12; $i++) {
            $ym = $cursor->format('Y-m');
            $months[] = $cursor->format('M Y');

            $paidSeries[] = (float) ($paidByMonth[$ym] ?? 0);
            $pendingSeries[] = (float) ($pendingByMonth[$ym] ?? 0);
            $overdueSeries[] = (float) ($overdueByMonth[$ym] ?? 0);

            $clientsRunning += (int) ($clientsByMonth[$ym] ?? 0);
            $clientsSeries[] = $clientsRunning;

            $cursor->addMonth();
        }

        $monthlyTrend = [
            'labels' => $months,
            'paid' => $paidSeries,
            'pending' => $pendingSeries,
        ];

        $statSeries = [
            'labels' => $months,
            'paid' => $paidSeries,
            'pending' => $pendingSeries,
            'overdue' => $overdueSeries,
            'clients' => $clientsSeries,
        ];

        /**
         * ✅ Calendar events: due dates + amount
         * (You can feed this into FullCalendar on the frontend)
         */
        $dueInvoices = (clone $base)
            ->with(['client:id,name'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today->copy()->startOfMonth())
            ->whereDate('due_date', '<=', $today->copy()->addMonths(2)->endOfMonth())
            ->orderBy('due_date')
            ->get(['id', 'number', 'client_id', 'due_date', 'total', 'status', 'currency_code']);

        $calendar = $dueInvoices->map(function ($inv) {
            $title = trim(($inv->number ?? 'Invoice').' • '.($inv->client?->name ?? 'Client'));

            return [
                'id' => $inv->id,
                'title' => $title,
                'date' => Carbon::parse($inv->due_date)->toDateString(),
                'status' => $inv->status,
                'amount' => (float) $inv->total,
                'currency_code' => $inv->currency_code,
                'url' => "/invoices/{$inv->id}",
            ];
        });

        /**
         * ✅ Top clients by outstanding (pending) amount
         */
        /**
         * Totalled per invoice rather than grouped in SQL, because each
         * invoice's own ledger has to come off it first. A client billed in two
         * currencies is still ranked on the converted sum rather than a raw one.
         */
        $outstandingInvoices = (clone $base)
            ->outstanding()
            ->withSum('payments as paid_sum', 'amount')
            ->get();

        $convertedByClient = [];

        foreach ($outstandingInvoices as $inv) {
            $clientId = (int) $inv->client_id;

            $convertedByClient[$clientId] = ($convertedByClient[$clientId] ?? 0.0) + $rollup->convert(
                $this->balances->unpaidRemainder($inv),
                (string) $inv->currency_code,
            );
        }

        arsort($convertedByClient);

        $clientNames = Client::query()
            ->whereIn('id', array_slice(array_keys($convertedByClient), 0, 8))
            ->pluck('name', 'id');

        $topClients = collect($convertedByClient)
            ->take(8)
            ->map(fn (float $total, int $clientId) => [
                'client_id' => $clientId,
                'name' => $clientNames[$clientId] ?? '—',
                'pending_total' => $total,
            ])
            ->values();

        return Inertia::render('dashboard', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'stats' => [
                /** Every money figure below is expressed in this currency. */
                'currency_code' => $displayCode,
                'precision' => $displayPrecision,

                'total_invoices' => $totalInvoices,
                'client_count' => $clientCount,

                'paid_count' => $paidCount,
                'pending_count' => $pendingCount,

                'paid_revenue' => $paidRevenue,
                'pending_revenue' => $pendingRevenue,

                'overdue_revenue' => $overdueRevenue,
                'due_in_7_revenue' => $dueIn7Revenue,
            ],
            'fx' => $rollup->meta(),
            'charts' => [
                'paid_vs_pending' => $paidVsPending,
                'monthly_trend' => $monthlyTrend,
                'stat_series' => $statSeries,
            ],
            'calendar' => $calendar,
            'top_clients' => $topClients,
        ]);
    }
}
