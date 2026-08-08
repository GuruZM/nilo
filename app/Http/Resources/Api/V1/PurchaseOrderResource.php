<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only document Nilo issues that points away from the customer, and the
 * only one whose currency is genuinely its own — you may order from an overseas
 * supplier in their money while billing clients in yours.
 *
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'reference' => $this->reference,
            'status' => $this->status,
            'issue_date' => $this->issue_date,
            'expected_date' => $this->expected_date,
            'currency_code' => $this->currency_code,
            'delivery_address' => $this->delivery_address,

            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'purchase_order_discount' => (float) ($this->purchase_order_discount ?? 0),
            'line_discount' => max(0, (float) $this->discount_total - (float) ($this->purchase_order_discount ?? 0)),
            'tax_percent' => (float) ($this->tax_percent ?? 0),
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,

            'notes' => $this->notes,
            'terms' => $this->terms,

            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'items' => DocumentItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
