<?php

namespace App\Services\Documents;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\InvoiceSettlement;
use Illuminate\Validation\ValidationException;

/**
 * How much credit an invoice can still absorb.
 *
 * A credit about to be applied may not exceed what the invoice is still owed,
 * or the ledger would show a negative balance. Drafts and voids are let through
 * because they move nothing.
 *
 * The balance is read outside the caller's transaction, so two credits raised
 * at the same instant can both see the same room and both be accepted. That is
 * the same concurrency gap as the payment cap, and gets the same v1 answer:
 * noted, not locked.
 */
class CreditNoteHeadroom
{
    public function __construct(private InvoiceSettlement $settlement) {}

    /**
     * `$ignoreNoteId` covers the case where the note under consideration is
     * *already* counted in the balance: its own total is added back so it
     * cannot block itself. The `applied()` scope is what makes that safe — a
     * draft or voided note sums to zero and nothing is added back, which is
     * right, because neither was ever subtracted.
     *
     * @throws ValidationException
     */
    public function guard(Invoice $invoice, float $total, string $status, ?int $ignoreNoteId = null): void
    {
        if (! in_array($status, CreditNote::APPLIED_STATUSES, true)) {
            return;
        }

        $available = $this->settlement->balanceDue($invoice);

        if ($ignoreNoteId) {
            $existing = (float) $invoice->creditNotes()
                ->applied()
                ->where('id', $ignoreNoteId)
                ->sum('total');

            $available = round($available + $existing, 2);
        }

        /**
         * Both figures are already rounded to the cent, so this epsilon only
         * absorbs binary float noise — it is two orders of magnitude below the
         * smallest real amount, and a credit one cent over is still refused.
         */
        if ($total > $available + 0.001) {
            throw ValidationException::withMessages([
                'items' => 'This credit of '.number_format($total, 2).' is more than the '
                    .number_format($available, 2).' still outstanding on invoice '
                    .$invoice->number.'.',
            ]);
        }
    }
}
