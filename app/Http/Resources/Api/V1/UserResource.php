<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'current_company_id' => $this->current_company_id,
            'current_currency_code' => $this->displayCurrencyCode(),
            'has_password' => $this->hasPassword(),
            'linked_providers' => $this->linkedProviders(),
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
