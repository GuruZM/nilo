<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * What an invoice is still owed, and the status that follows from it.
 *
 * Status is derived here rather than set by hand anywhere a payment is touched,
 * so cash and credit can never disagree about whether an invoice is settled.
 * Credit notes join this calculation through {@see amountCredited()}.
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
     * Credits raised against this invoice. Drafts are excluded — a credit note
     * that is still being written has not been given to anyone and must not
     * move the balance.
     */
    public function amountCredited(Invoice $invoice): float
    {
        return round((float) $invoice->creditNotes()->applied()->sum('total'), 2);
    }

    public function balanceDue(Invoice $invoice): float
    {
        $settled = $this->amountPaid($invoice) + $this->amountCredited($invoice);

        return round(max(0, (float) $invoice->total - $settled), 2);
    }

    /**
     * Rewrites the invoice status from the ledger.
     *
     * Safe to call on any invoice: one whose ledger says nothing is settled is
     * left as it is, unless it is currently carrying a settled status this
     * service itself set, in which case it comes to rest at `sent`. `sent`
     * rather than `pending` is that resting state, because an invoice that has
     * had money against it has demonstrably reached the client.
     */
    public function sync(Invoice $invoice): void
    {
        if (in_array($invoice->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $settled = $this->amountPaid($invoice) + $this->amountCredited($invoice);
        $balance = round(max(0, (float) $invoice->total - $settled), 2);

        $status = match (true) {
            $balance <= 0.0 => Invoice::STATUS_PAID,
            $settled > 0.0 => Invoice::STATUS_PARTIALLY_PAID,

            /**
             * Nothing is settled. Only reverse a status this service itself
             * could have set — promoting a draft that was never issued would
             * pull unsent money into every outstanding roll-up.
             */
            in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true) => 'sent',

            default => $invoice->status,
        };

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }
}
