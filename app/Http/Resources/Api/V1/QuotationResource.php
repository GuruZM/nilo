<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
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
            'valid_until' => $this->valid_until,
            'currency_code' => $this->currency_code,

            'subtotal' => (float) $this->subtotal,
            'discount_total' => (float) $this->discount_total,
            'quotation_discount' => (float) ($this->quotation_discount ?? 0),

            /** `discount_total` carries both; split it so neither is shown twice. */
            'line_discount' => max(0, (float) $this->discount_total - (float) ($this->quotation_discount ?? 0)),

            'tax_percent' => (float) ($this->tax_percent ?? 0),
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,

            'notes' => $this->notes,
            'terms' => $this->terms,

            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'items' => DocumentItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
