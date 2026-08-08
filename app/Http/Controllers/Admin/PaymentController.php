<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Dpo\DpoReconciler;
use App\Services\SubscriptionActivator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(private SubscriptionActivator $activator) {}

    public function index(Request $request): \Inertia\Response
    {
        $query = Payment::with(['user', 'plan']);

        if ($status = $request->input('status', 'pending')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $method = $request->input('method', 'all');

        if ($method === 'dpo') {
            $query->where('payment_method', Payment::METHOD_DPO);
        } elseif ($method === 'manual') {
            $query->where('payment_method', '!=', Payment::METHOD_DPO);
        }

        $payments = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/payments/index', [
            'payments' => $payments,
            'filters' => [
                'status' => $status,
                'method' => $method,
            ],
        ]);
    }

    public function show(Payment $payment): \Inertia\Response
    {
        $payment->load(['user', 'plan', 'subscription', 'confirmedBy', 'coupon']);

        return Inertia::render('admin/payments/show', [
            // Hidden from the subscriber's own view; an admin chasing a failed
            // charge needs DPO's verbatim answer.
            'payment' => $payment->makeVisible('gateway_response'),
        ]);
    }

    public function confirm(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $this->refuseGateway($payment);

        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $this->activator->activate($payment, $request->user(), $request->admin_notes);

        return back()->with('success', 'Payment confirmed and subscription activated.');
    }

    public function reject(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $this->refuseGateway($payment);

        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $this->activator->reject($payment, $request->user(), $request->admin_notes);

        return back()->with('success', 'Payment rejected.');
    }

    /**
     * Re-ask DPO where a stuck gateway payment actually stands.
     *
     * This is the only admin action a gateway payment gets: the right move on
     * one that never resolved is to fetch the truth, not to assert it.
     */
    public function verify(Payment $payment, DpoReconciler $reconciler): \Illuminate\Http\RedirectResponse
    {
        abort_unless($payment->isGateway() && $payment->dpo_transaction_token, 404);

        $status = $reconciler->reconcileOne($payment);

        return back()->with('success', "DPO reports this payment as {$status}.");
    }

    /**
     * A gateway payment is settled by DPO and nobody else. Hand-confirming one
     * would activate a subscription against money that may never have moved.
     */
    private function refuseGateway(Payment $payment): void
    {
        abort_if($payment->isGateway(), 403, 'Gateway payments are settled by DPO. Use Verify instead.');
    }
}
