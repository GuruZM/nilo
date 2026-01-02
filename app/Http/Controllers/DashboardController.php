<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $activeCompanyId = session('active_company_id');
        $company = $user->companies()->where('companies.id', $activeCompanyId)->first();
        if (!$company) {
            // fallback to first company
            $company = $user->companies()->first();
        }
        if (!$company) {
            return Inertia::render('dashboard', [
                'stats' => null,
                'invoices' => [],
                'company' => null,
            ]);
        }

        // Example analytics
        $invoices = $company->invoices()->latest()->take(10)->get();
        $totalRevenue = $company->invoices()->where('status', 'Paid')->sum('amount');
        $unpaidCount = $company->invoices()->where('status', 'Unpaid')->count();
        $clientCount = $company->clients()->count();

        return Inertia::render('dashboard', [
            'stats' => [
                'total_revenue' => $totalRevenue,
                'unpaid_count' => $unpaidCount,
                'client_count' => $clientCount,
            ],
            'invoices' => $invoices,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
        ]);
    }
}
