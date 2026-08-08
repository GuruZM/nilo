<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

/**
 * Writes a purchase order and its lines. See {@see CreateInvoice} for why the
 * plan limit check stays with the caller.
 */
class CreatePurchaseOrder
{
    public function __construct(private TemplateProvisioner $templates) {}

    /**
     * @param  array<string, mixed>  $data  Already validated by DocumentRules::purchaseOrder().
     */
    public function handle(int $companyId, ?User $user, array $data): PurchaseOrder
    {
        $totals = DocumentTotals::compute(
            $data['items'],
            (float) ($data['purchase_order_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        return DB::transaction(function () use ($companyId, $user, $data, $totals): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'company_id' => $companyId,
                'supplier_id' => (int) $data['supplier_id'],

                /**
                 * Provisioning runs inside the transaction on purpose: it may
                 * create the company's first purchase order template, and a
                 * rolled back order must not leave that row behind.
                 */
                'purchase_order_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::PurchaseOrder)->id,

                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(PurchaseOrder::class, $companyId, DocumentType::PurchaseOrder),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'issue_date' => $data['issue_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency_code' => strtoupper((string) $data['currency_code']),
                'delivery_address' => $data['delivery_address'] ?? null,

                'subtotal' => $totals['subtotal'],
                'purchase_order_discount' => $totals['document_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $order->items()->createMany($totals['items']);

            return $order;
        });
    }
}
