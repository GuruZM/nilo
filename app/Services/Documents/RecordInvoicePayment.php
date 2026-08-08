<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\InvoiceSettlement;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Records money received against an invoice.
 *
 * The invoice status is never written here — {@see InvoiceSettlement} derives
 * it from the ledger, so recording and removing cannot disagree about whether
 * an invoice is settled.
 */
class RecordInvoicePayment
{
    public function __construct(private InvoiceSettlement $settlement) {}

    /**
     * @param  array<string, mixed>  $data  Already validated by DocumentRules::invoicePayment().
     */
    public function handle(int $companyId, ?User $user, Invoice $invoice, array $data): InvoicePayment
    {
        return DB::transaction(function () use ($companyId, $user, $invoice, $data): InvoicePayment {
            $payment = InvoicePayment::query()->create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'recorded_by' => $user?->id,
                'receipt_number' => DocumentNumber::nextFor(
                    InvoicePayment::class,
                    $companyId,
                    DocumentType::Receipt,
                    'receipt_number',
                ),
                'amount' => (float) $data['amount'],

                /** Never the request's — a receipt is denominated by its invoice. */
                'currency_code' => $invoice->currency_code,

                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);

            /**
             * Order matters. The payment row must exist before either of the
             * next two steps, because both read the ledger: `sync` to derive
             * the status, `balanceDue` to get the figure this receipt will
             * carry for ever. Stamping is last and writes only `balance_after`,
             * a column neither of them reads, so it cannot disturb what they
             * just computed and needs no second sync.
             */
            $invoice = $invoice->fresh();

            $this->settlement->sync($invoice);

            $payment->update([
                'balance_after' => $this->settlement->balanceDue($invoice),
            ]);

            return $payment;
        });
    }

    public function remove(Invoice $invoice, InvoicePayment $payment): void
    {
        DB::transaction(function () use ($invoice, $payment): void {
            $payment->delete();

            $this->settlement->sync($invoice->fresh());
        });
    }
}
