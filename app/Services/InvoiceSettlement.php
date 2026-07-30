<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * What an invoice is still owed, and the status that follows from it.
 *
 * Status is derived here rather than set by hand anywhere a payment is touched,
 * so cash and credit can never disagree about whether an invoice is settled.
 * Credit notes join this calculation in Phase 2 through {@see amountCredited()}.
 */
class InvoiceSettlement
{
    /**
     * Statuses this service refuses to overwrite. A voided invoice is a closed
     * book — recording money against it must not quietly reopen it.
     *
     * @var list<string>
     */
    private const TERMINAL_STATUSES = [Invoice::STATUS_VOID];

    public function amountPaid(Invoice $invoice): float
    {
        return round((float) $invoice->payments()->sum('amount'), 2);
    }

    /**
     * Credits raised against this invoice. Always zero until Phase 2 lands the
     * credit_notes table.
     */
    public function amountCredited(Invoice $invoice): float
    {
        return 0.0;
    }

    public function balanceDue(Invoice $invoice): float
    {
        $settled = $this->amountPaid($invoice) + $this->amountCredited($invoice);

        return round(max(0, (float) $invoice->total - $settled), 2);
    }

    /**
     * Rewrites the invoice status from the ledger.
     *
     * `sent` rather than `pending` is the resting state for an unsettled
     * invoice that has had money on it, because anything with a payment
     * against it has demonstrably reached the client.
     */
    public function sync(Invoice $invoice): void
    {
        if (in_array($invoice->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $balance = $this->balanceDue($invoice);
        $settled = $this->amountPaid($invoice) + $this->amountCredited($invoice);

        $status = match (true) {
            $balance <= 0.0 => Invoice::STATUS_PAID,
            $settled > 0.0 => Invoice::STATUS_PARTIALLY_PAID,
            default => 'sent',
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }
}
