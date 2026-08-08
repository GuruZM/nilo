<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'currency_code' => $this->currency_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'tpin' => $this->tpin,
            'address' => $this->address,
            'logo_url' => $this->logo_url,
            'primary_color' => $this->primary_color,
            'is_owner' => (bool) ($this->pivot->is_owner ?? false),
        ];
    }
}
