<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money still owed across a set of invoices, in a rollup's display currency.
 *
 * An invoice's total is what it was billed for, not what is left on it, so the
 * ledger comes off each invoice before anything is added up. The subtraction
 * happens in the invoice's own currency and the *remainder* is converted — a
 * payment is received in the currency the invoice was raised in, so converting
 * the total and deducting afterwards would be arithmetic across two currencies.
 */
class OutstandingBalance
{
    /**
     * `withSum` keeps the ledger a correlated subquery rather than one query
     * per invoice.
     */
    public function forQuery(Builder $query, CurrencyRollup $rollup): float
    {
        return (float) $query
            ->withSum('payments as paid_sum', 'amount')
            ->get()
            ->sum(fn (Invoice $invoice) => $rollup->convert(
                $this->unpaidRemainder($invoice),
                (string) $invoice->currency_code,
            ));
    }

    /**
     * What is left on one invoice, in its own currency. Requires `paid_sum`.
     *
     * Floored at zero: an overpayment settles the invoice, it does not turn
     * into negative money owed elsewhere.
     */
    public function unpaidRemainder(Invoice $invoice): float
    {
        return max(0, (float) $invoice->total - (float) ($invoice->paid_sum ?? 0));
    }
}
