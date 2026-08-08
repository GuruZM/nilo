<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Issues a receipt for money received where no invoice was raised.
 *
 * The counterpart to {@see RecordInvoicePayment}, which stays the settlement
 * path. Nothing here touches {@see \App\Services\InvoiceSettlement}: there is no
 * invoice to re-derive a status for, and `balance_after` is left null because a
 * payment against no invoice leaves no balance behind.
 */
class IssueReceipt
{
    /**
     * @param  array<string, mixed>  $data  Already validated by DocumentRules::standaloneReceipt().
     */
    public function handle(int $companyId, ?User $user, array $data): InvoicePayment
    {
        /**
         * The number is read and written inside one transaction because
         * {@see DocumentNumber} has no sequence table — two concurrent callers
         * would otherwise both read the same highest number.
         */
        return DB::transaction(fn (): InvoicePayment => InvoicePayment::query()->create([
            'company_id' => $companyId,
            'invoice_id' => null,
            'client_id' => $data['client_id'],
            'recorded_by' => $user?->id,
            'receipt_number' => DocumentNumber::nextFor(
                InvoicePayment::class,
                $companyId,
                DocumentType::Receipt,
                'receipt_number',
            ),
            'amount' => (float) $data['amount'],
            'currency_code' => $data['currency_code'],
            'paid_on' => $data['paid_on'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
        ]));
    }
}
