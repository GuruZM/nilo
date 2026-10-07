<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

/**
 * Turns an accepted quote into the invoice that bills it.
 *
 * The lines are copied rather than referenced: the client agreed to the prices
 * on the sheet they were sent, so editing the quotation afterwards must not
 * silently change what they are being charged.
 *
 * At most one invoice per quotation. The caller checks first so it can give a
 * civil answer, but two concurrent clicks both pass that check — the unique
 * index on `invoices.quotation_id` is what actually settles it.
 */
class CreateInvoiceFromQuotation
{
    public function __construct(private TemplateProvisioner $templates) {}

    public function handle(int $companyId, ?User $user, Quotation $quotation): Invoice
    {
        $quotation->loadMissing('items');

        /**
         * Recomputed from the copied lines rather than copied off the
         * quotation row. Same lines, same discount, same rate in means
         * identical figures out, and it keeps {@see DocumentTotals} the only
         * place in the app that decides what a document adds up to.
         */
        $totals = DocumentTotals::compute(
            $quotation->items->map(fn (QuotationItem $item): array => [
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
            ])->all(),
            (float) ($quotation->quotation_discount ?? 0),
            (float) ($quotation->tax_percent ?? 0),
        );

        $today = now()->toDateString();

        return DB::transaction(function () use ($companyId, $user, $quotation, $totals, $today): Invoice {
            $invoice = Invoice::query()->create([
                'company_id' => $companyId,
                'client_id' => $quotation->client_id,
                'quotation_id' => $quotation->id,

                /**
                 * Provisioned rather than required. A company that has only
                 * ever built quotation templates can still bill what it quoted;
                 * making it design an invoice template first would be a wall.
                 */
                'invoice_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::Invoice)->id,
                'created_by' => $user?->id,

                /** Read inside the transaction. {@see DocumentNumber} for why. */
                'number' => DocumentNumber::nextFor(Invoice::class, $companyId, DocumentType::Invoice),

                /** Printed on the sheet; the foreign key above is what queries use. */
                'reference' => $quotation->number,
                'title' => $quotation->title,

                /**
                 * Dated today, not on the quotation's dates. The bill is being
                 * raised now, and a quotation's `valid_until` is when the price
                 * expires — not when the money is due.
                 */
                'issue_date' => $today,
                'due_date' => $today,

                'currency_code' => $quotation->currency_code,

                'subtotal' => $totals['subtotal'],
                'invoice_discount' => $totals['document_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => 'pending',

                'has_delivery_note' => false,
                'is_recurring' => false,

                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
            ]);

            $invoice->items()->createMany($totals['items']);

            /**
             * Billing a quote is accepting it. An already accepted one needs no
             * help, and an expired one is left expired — a client can come back
             * late and still be invoiced, but rewriting the status would
             * falsify the record of what happened.
             */
            if (in_array($quotation->status, ['draft', 'sent'], true)) {
                $quotation->update(['status' => 'accepted']);
            }

            return $invoice;
        });
    }
}
