<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use App\Services\InvoiceSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One invoice in full.
 *
 * The field names and casts are lifted from InvoiceController::show() so the
 * phone and the browser describe the same invoice the same way.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settlement = app(InvoiceSettlement::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'reference' => $this->reference,
            'status' => $this->status,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'currency_code' => $this->currency_code,

            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'invoice_discount' => (float) ($this->invoice_discount ?? 0),

            /** `discount_total` carries both; split it so neither is shown twice. */
            'line_discount' => (float) $this->discount_total - (float) ($this->invoice_discount ?? 0),

            'tax_percent' => (float) ($this->tax_percent ?? 0),
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,

            'notes' => $this->notes,
            'terms' => $this->terms,

            'has_delivery_note' => (bool) $this->has_delivery_note,
            'is_recurring' => (bool) $this->is_recurring,

            'client' => ClientResource::make($this->whenLoaded('client')),
            'items' => DocumentItemResource::collection($this->whenLoaded('items')),

            'amount_paid' => $settlement->amountPaid($this->resource),
            'amount_credited' => $settlement->amountCredited($this->resource),
            'balance_due' => $settlement->balanceDue($this->resource),

            /** Newest first — {@see Invoice::payments()} orders the ledger. */
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
