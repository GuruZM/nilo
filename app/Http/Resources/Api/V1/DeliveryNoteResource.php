<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery note carries no money at all — not a price, not a total, not even
 * a currency. Its items are quantities only. Adding any of that back would
 * turn a dispatch record into something that looks like a bill.
 *
 * @mixin DeliveryNote
 */
class DeliveryNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'reference' => $this->reference,
            'status' => $this->status,

            /**
             * Dates are sent as `Y-m-d` rather than serialised Carbon, matching
             * what the web sign-off form needs — a date input renders blank for
             * anything else.
             */
            'issue_date' => $this->issue_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),

            'deliver_to' => $this->deliver_to,
            'delivery_address' => $this->delivery_address,
            'received_by' => $this->received_by,
            'received_on' => $this->received_on?->toDateString(),
            'notes' => $this->notes,

            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->number),
            'client' => ClientResource::make($this->whenLoaded('client')),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'sort_order' => (int) $item->sort_order,
            ])),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
