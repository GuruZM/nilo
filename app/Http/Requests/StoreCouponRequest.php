<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    /**
     * The admin middleware already gates the route to super admins.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Codes are compared case-insensitively at redemption, so they are
     * upper-cased before the uniqueness rule runs rather than after.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Coupon::normalizeCode((string) $this->input('code'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('coupons', 'code')],
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => [
                'required',
                'numeric',
                'min:0.01',
                $this->input('discount_type') === Coupon::TYPE_PERCENTAGE ? 'max:100' : 'max:999999999',
            ],
            'currency_code' => 'required_if:discount_type,fixed|nullable|string|size:3',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'max_redemptions' => 'nullable|integer|min:1',
            'once_per_user' => 'boolean',
            'is_active' => 'boolean',
            'plan_ids' => 'array',
            'plan_ids.*' => 'integer|exists:plans,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code may only contain uppercase letters, numbers and hyphens.',
            'code.unique' => 'Another coupon already uses this code.',
            'discount_value.max' => 'A percentage discount cannot exceed 100.',
            'currency_code.required_if' => 'Pick the currency a fixed discount is denominated in.',
            'expires_at.after' => 'The expiry date must fall after the start date.',
            'max_redemptions.min' => 'Leave this blank for unlimited redemptions.',
        ];
    }
}
