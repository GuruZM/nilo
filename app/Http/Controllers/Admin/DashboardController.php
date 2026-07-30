<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseInquiry;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(): \Inertia\Response
    {
        $totalUsers = User::count();
        $recentUsers = User::latest()->take(10)->get(['id', 'name', 'email', 'created_at']);

        // Subscription stats by plan
        $planStats = Plan::withCount(['subscriptions' => function ($q) {
            $q->where('status', 'active');
        }])->orderBy('sort_order')->get(['id', 'name', 'slug', 'subscriptions_count']);

        $pendingPayments = Payment::where('status', 'pending')->count();
        $confirmedRevenue = Payment::where('status', 'confirmed')->sum('amount');
        $pendingInquiries = EnterpriseInquiry::where('is_handled', false)->count();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'totalUsers' => $totalUsers,
                'pendingPayments' => $pendingPayments,
                'confirmedRevenue' => $confirmedRevenue,
                'pendingInquiries' => $pendingInquiries,
            ],
            'planStats' => $planStats,
            'recentUsers' => $recentUsers,
        ]);
    }
}
