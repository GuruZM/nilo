<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoicePayment
 */
class InvoicePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'receipt_number' => $this->receipt_number,
            'amount' => (float) $this->amount,
            'currency_code' => $this->currency_code,
            'paid_on' => $this->paid_on?->toDateString(),
            'method' => $this->method,
            'method_label' => $this->methodLabel(),
            'reference' => $this->reference,
            'balance_after' => $this->balance_after === null ? null : (float) $this->balance_after,
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
        ];
    }
}
