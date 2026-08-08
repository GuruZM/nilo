<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNote
 */
class CreditNoteResource extends JsonResource
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
            'reason' => $this->reason,
            'issue_date' => $this->issue_date,

            /** Always the credited invoice's currency, never one the client chose. */
            'currency_code' => $this->currency_code,

            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'credit_note_discount' => (float) ($this->credit_note_discount ?? 0),
            'line_discount' => max(0, (float) $this->discount_total - (float) ($this->credit_note_discount ?? 0)),
            'tax_percent' => (float) ($this->tax_percent ?? 0),
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,

            'notes' => $this->notes,
            'terms' => $this->terms,

            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->number),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'items' => DocumentItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
