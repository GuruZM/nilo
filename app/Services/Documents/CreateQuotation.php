<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Quotation;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

/**
 * Writes a quotation and its lines. See {@see CreateInvoice} for why the plan
 * limit and prerequisites checks stay with the caller.
 */
class CreateQuotation
{
    /**
     * @param  array<string, mixed>  $data  Already validated by DocumentRules::quotation().
     */
    public function handle(int $companyId, ?User $user, array $data): Quotation
    {
        $totals = DocumentTotals::compute(
            $data['items'],
            (float) ($data['quotation_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        return DB::transaction(function () use ($companyId, $user, $data, $totals): Quotation {
            $quotation = Quotation::query()->create([
                'company_id' => $companyId,
                'client_id' => (int) $data['client_id'],
                'quotation_template_id' => (int) $data['quotation_template_id'],
                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(Quotation::class, $companyId, DocumentType::Quotation),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'currency_code' => strtoupper((string) $data['currency_code']),

                'subtotal' => $totals['subtotal'],

                /** Both are kept so the UI can show the breakdown. */
                'quotation_discount' => $totals['document_discount'],
                'discount_total' => $totals['discount_total'],

                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $quotation->items()->createMany($totals['items']);

            return $quotation;
        });
    }
}
