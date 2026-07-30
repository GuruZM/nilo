<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * Identical to creating one, except the code has to stay unique against
     * every coupon but this one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('coupons', 'code')->ignore($this->route('coupon'))],
        ];
    }
}
