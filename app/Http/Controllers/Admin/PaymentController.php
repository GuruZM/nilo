<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(private CouponService $coupons) {}

    public function index(Request $request): \Inertia\Response
    {
        $query = Payment::with(['user', 'plan']);

        if ($status = $request->input('status', 'pending')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $payments = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/payments/index', [
            'payments' => $payments,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function show(Payment $payment): \Inertia\Response
    {
        $payment->load(['user', 'plan', 'subscription', 'confirmedBy', 'coupon']);

        return Inertia::render('admin/payments/show', [
            'payment' => $payment,
        ]);
    }

    public function confirm(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $payment->update([
            'status' => 'confirmed',
            'admin_notes' => $request->admin_notes,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        // Activate the associated subscription
        if ($payment->subscription) {
            $start = now();

            $payment->subscription->update([
                'status' => 'active',
                'starts_at' => $start,
                'ends_at' => $payment->plan->periodEndFrom($start),
            ]);
        }

        return back()->with('success', 'Payment confirmed and subscription activated.');
    }

    public function reject(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $payment->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        // Cancel the associated subscription
        if ($payment->subscription) {
            $payment->subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        // The customer never paid, so they should not have burnt their coupon.
        $this->coupons->release($payment);

        return back()->with('success', 'Payment rejected.');
    }
}
