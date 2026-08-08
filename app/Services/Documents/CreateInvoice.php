<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

/**
 * Writes an invoice and its lines.
 *
 * Shared by the web controller and the mobile API so both produce identical
 * rows from identical input. Everything upstream of the write — the plan
 * limit, the prerequisites gate, validation — stays with the caller, because
 * the two clients report those refusals differently: the browser flashes a
 * dialog, the API returns a status.
 */
class CreateInvoice
{
    /**
     * @param  array<string, mixed>  $data  Already validated by DocumentRules::invoice().
     */
    public function handle(int $companyId, ?User $user, array $data): Invoice
    {
        $data = $this->normaliseRecurrence($data);

        $totals = DocumentTotals::compute(
            $data['items'],
            (float) ($data['invoice_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        return DB::transaction(function () use ($companyId, $user, $data, $totals): Invoice {
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'client_id' => (int) $data['client_id'],
                'invoice_template_id' => $data['invoice_template_id'] ?? null,
                'created_by' => $user?->id,

                /**
                 * Read inside the transaction so the scan and the insert cannot
                 * be separated. {@see DocumentNumber} for why concurrent callers
                 * still race and the unique index settles it.
                 */
                'number' => DocumentNumber::nextFor(Invoice::class, $companyId, DocumentType::Invoice),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,

                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,

                'currency_code' => strtoupper($data['currency_code']),

                'subtotal' => $totals['subtotal'],

                /** Both are kept so the UI can show the breakdown. */
                'invoice_discount' => $totals['document_discount'],
                'discount_total' => $totals['discount_total'],

                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],

                'has_delivery_note' => (bool) $data['has_delivery_note'],

                'is_recurring' => (bool) $data['is_recurring'],
                'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
                'recurrence_interval' => $data['recurrence_interval'] ?? null,
                'recurrence_start_date' => $data['recurrence_start_date'] ?? null,
                'recurrence_end_date' => $data['recurrence_end_date'] ?? null,
                'next_run_at' => $data['next_run_at'] ?? null,

                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            foreach ($totals['items'] as $row) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $row['description'],
                    'unit' => $row['unit'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount' => $row['discount'] ?? 0,
                    'tax' => $row['tax'] ?? 0,
                    'line_total' => $row['line_total'],
                    'sort_order' => $row['sort_order'] ?? 0,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * A non-recurring invoice must carry no recurrence at all, so the fields
     * are cleared rather than left to whatever the client happened to send —
     * otherwise unticking the box would leave a schedule behind.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function normaliseRecurrence(array $data): array
    {
        if (! (bool) $data['is_recurring']) {
            return [
                ...$data,
                'recurrence_frequency' => null,
                'recurrence_interval' => null,
                'recurrence_start_date' => null,
                'recurrence_end_date' => null,
                'next_run_at' => null,
            ];
        }

        if (empty($data['recurrence_frequency'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recurrence_frequency' => 'Select a recurrence frequency.',
            ]);
        }

        $data['recurrence_interval'] = $data['recurrence_interval'] ?: 1;

        /** next_run_at: later we'll calculate properly (cron), for now anchor it. */
        $data['next_run_at'] = $data['recurrence_start_date'] ?? $data['issue_date'];

        return $data;
    }
}
