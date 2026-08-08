<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line on any priced document. Invoice, quotation, credit note and purchase
 * order items all carry the same columns, so one resource serves them all.
 */
class DocumentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'line_total' => (float) $this->line_total,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
