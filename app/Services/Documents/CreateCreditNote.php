<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceSettlement;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

/**
 * Writes a credit note against an invoice and re-derives that invoice's status.
 *
 * The headroom check — that an applied credit cannot exceed what the invoice
 * is still owed — stays with the caller, because it reports differently on each
 * client. See {@see CreateInvoice}.
 */
class CreateCreditNote
{
    public function __construct(
        private TemplateProvisioner $templates,
        private InvoiceSettlement $settlement,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Already validated by DocumentRules::creditNote().
     */
    public function handle(int $companyId, ?User $user, Invoice $invoice, array $data): CreditNote
    {
        $totals = $this->totals($data);

        return DB::transaction(function () use ($companyId, $user, $data, $totals, $invoice): CreditNote {
            $note = CreditNote::query()->create([
                'company_id' => $companyId,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,

                /**
                 * Provisioning happens inside the transaction on purpose: it may
                 * create the company's first credit note template, and a rolled
                 * back note must not leave that row behind.
                 */
                'credit_note_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::CreditNote)->id,

                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(CreditNote::class, $companyId, DocumentType::CreditNote),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'reason' => $data['reason'] ?? null,
                'issue_date' => $data['issue_date'],

                /** Never the submitted currency — a credit must match what it credits. */
                'currency_code' => $invoice->currency_code,

                'subtotal' => $totals['subtotal'],
                'credit_note_discount' => $totals['document_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $note->items()->createMany($totals['items']);

            $this->settlement->sync($invoice->fresh());

            return $note;
        });
    }

    /**
     * Exposed so a caller can check the credit fits before committing to it —
     * the headroom guard needs the total, and computing it twice would risk the
     * checked figure and the written one disagreeing.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function totals(array $data): array
    {
        return DocumentTotals::compute(
            $data['items'],
            (float) ($data['credit_note_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );
    }
}
