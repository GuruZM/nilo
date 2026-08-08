<?php

namespace App\Services\Dpo;

use App\Models\Payment;
use App\Services\SubscriptionActivator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settles payments that never came back from DPO.
 *
 * A customer who closes the tab mid-payment leaves a row stuck at `pending`
 * even though the money moved. DPO holds a transaction for its full PTL, so
 * asking it directly is the only way to find out.
 */
class DpoReconciler
{
    public function __construct(
        private DpoClient $client,
        private SubscriptionActivator $activator,
    ) {}

    public function reconcile(int $olderThanMinutes = 15, int $limit = 200): DpoReconcileResult
    {
        $payments = Payment::query()
            ->where('payment_method', Payment::METHOD_DPO)
            ->where('status', 'pending')
            ->whereNotNull('dpo_transaction_token')
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))
            ->with(['plan', 'subscription'])
            ->latest()
            ->limit($limit)
            ->get();

        $activated = $rejected = $stillPending = $errors = 0;

        foreach ($payments as $payment) {
            try {
                match ($this->reconcileOne($payment)) {
                    'paid' => $activated++,
                    'pending' => $stillPending++,
                    default => $rejected++,
                };
            } catch (Throwable $e) {
                // One unreachable transaction must not strand every other
                // customer waiting behind it in the same run.
                $errors++;

                Log::error('DPO reconciliation failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return DpoReconcileResult::make($payments->count(), $activated, $rejected, $stillPending, $errors);
    }

    /**
     * Ask DPO about one payment and act on the answer.
     *
     * Returns the gateway status so callers can report it. Safe to call on an
     * already-settled payment — activation is guarded by its own lock.
     */
    public function reconcileOne(Payment $payment): string
    {
        $result = $this->client->verifyToken($payment->dpo_transaction_token);

        $payment->update([
            'gateway_status' => $result->status,
            'gateway_response' => $result->raw,
            'verified_at' => now(),
        ]);

        if ($result->status === 'paid') {
            if ($this->activator->activate($payment)) {
                $payment->forceFill(['paid_at' => now()])->save();
            }
        } elseif ($result->isTerminal() && $payment->isPending()) {
            $this->activator->reject($payment);
        }

        return $result->status;
    }
}
