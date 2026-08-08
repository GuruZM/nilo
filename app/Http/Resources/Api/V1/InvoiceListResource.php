<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice as it appears in a list.
 *
 * Deliberately lighter than {@see InvoiceResource}: the settlement figures on
 * that one each read the ledger, which would be a query per row on a paged
 * list. A list shows what is owed in outline; the detail screen fetches the
 * rest.
 *
 * @mixin Invoice
 */
class InvoiceListResource extends JsonResource
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
            'status' => $this->status,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'currency_code' => $this->currency_code,
            'total' => (float) $this->total,
            'is_recurring' => (bool) $this->is_recurring,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
