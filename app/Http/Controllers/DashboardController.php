<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class DashboardController extends Controller
{
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
            'recent_invoices' => [],
            'top_clients' => [],
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
     * Assumes Invoice columns: total, status (pending|paid), due_date, issue_date, client_id
     */
    $base = Invoice::query()->where('company_id', $companyId);

    $totalInvoices = (clone $base)->count();
    $paidCount = (clone $base)->where('status', 'paid')->count();
    $pendingCount = (clone $base)->where('status', 'pending')->count();

    $paidRevenue = (float) (clone $base)->where('status', 'paid')->sum('total');
    $pendingRevenue = (float) (clone $base)->where('status', 'pending')->sum('total');

    $overdueRevenue = (float) (clone $base)
        ->where('status', 'pending')
        ->whereNotNull('due_date')
        ->whereDate('due_date', '<', $today)
        ->sum('total');

    $dueIn7Revenue = (float) (clone $base)
        ->where('status', 'pending')
        ->whereNotNull('due_date')
        ->whereBetween(DB::raw('DATE(due_date)'), [$today->toDateString(), $in7->toDateString()])
        ->sum('total');

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
    $trendRows = (clone $base)
        ->whereDate('issue_date', '>=', $start12->toDateString())
        ->selectRaw("to_char(issue_date, 'YYYY-MM') as ym")
        ->selectRaw("SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_total")
        ->selectRaw("SUM(CASE WHEN status = 'pending' THEN total ELSE 0 END) as pending_total")
        ->groupBy('ym')
        ->orderBy('ym')
        ->get();

    // Ensure all months exist
    $months = [];
    $paidSeries = [];
    $pendingSeries = [];

    $cursor = $start12->copy();
    $map = $trendRows->keyBy('ym');

    for ($i = 0; $i < 12; $i++) {
        $ym = $cursor->format('Y-m');
        $months[] = $cursor->format('M Y');

        $row = $map->get($ym);
        $paidSeries[] = (float) ($row->paid_total ?? 0);
        $pendingSeries[] = (float) ($row->pending_total ?? 0);

        $cursor->addMonth();
    }

    $monthlyTrend = [
        'labels' => $months,
        'paid' => $paidSeries,
        'pending' => $pendingSeries,
    ];

    /**
     * ✅ Aging buckets for pending invoices
     */
    $aging = [
        'overdue' => [
            'count' => (clone $base)->where('status','pending')->whereNotNull('due_date')->whereDate('due_date','<',$today)->count(),
            'amount' => $overdueRevenue,
        ],
        'due_in_7_days' => [
            'count' => (clone $base)->where('status','pending')->whereNotNull('due_date')
                ->whereBetween(DB::raw('DATE(due_date)'), [$today->toDateString(), $in7->toDateString()])
                ->count(),
            'amount' => $dueIn7Revenue,
        ],
        'due_later' => [
            'count' => (clone $base)->where('status','pending')->whereNotNull('due_date')->whereDate('due_date','>',$in7)->count(),
            'amount' => (float) (clone $base)->where('status','pending')->whereNotNull('due_date')->whereDate('due_date','>',$in7)->sum('total'),
        ],
        'no_due_date' => [
            'count' => (clone $base)->where('status','pending')->whereNull('due_date')->count(),
            'amount' => (float) (clone $base)->where('status','pending')->whereNull('due_date')->sum('total'),
        ],
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
        ->get(['id','number','client_id','due_date','total','status','currency_code']);

    $calendar = $dueInvoices->map(function ($inv) {
        $title = trim(($inv->number ?? 'Invoice') . ' • ' . ($inv->client?->name ?? 'Client'));
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
     * ✅ Recent invoices list
     */
    $recentInvoices = (clone $base)
        ->with(['client:id,name'])
        ->orderByDesc('created_at')
        ->limit(10)
        ->get(['id','number','client_id','issue_date','due_date','total','status','currency_code','created_at']);

    /**
     * ✅ Top clients by outstanding (pending) amount
     */
    $topClients = (clone $base)
        ->where('status', 'pending')
        ->select('client_id')
        ->selectRaw('SUM(total) as pending_total')
        ->groupBy('client_id')
        ->orderByDesc('pending_total')
        ->limit(8)
        ->get()
        ->load('client:id,name')
        ->map(fn ($r) => [
            'client_id' => $r->client_id,
            'name' => $r->client?->name ?? '—',
            'pending_total' => (float) $r->pending_total,
        ]);

    return Inertia::render('dashboard', [
        'company' => [
            'id' => $company->id,
            'name' => $company->name,
        ],
        'stats' => [
            'total_invoices' => $totalInvoices,
            'client_count' => $clientCount,

            'paid_count' => $paidCount,
            'pending_count' => $pendingCount,

            'paid_revenue' => $paidRevenue,
            'pending_revenue' => $pendingRevenue,

            'overdue_revenue' => $overdueRevenue,
            'due_in_7_revenue' => $dueIn7Revenue,

            // optional (nice for UI labels)
            'as_of' => $today->toDateString(),
        ],
        'charts' => [
            'paid_vs_pending' => $paidVsPending,
            'monthly_trend' => $monthlyTrend,
            'aging' => $aging,
        ],
        'calendar' => $calendar,
        'recent_invoices' => $recentInvoices,
        'top_clients' => $topClients,
    ]);
}
}
