<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    /**
     * The route already requires an authenticated, verified user; whether the
     * plan itself may be purchased is settled in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            'payment_method' => 'required|in:airtel_money,bank_transfer',
            'phone_number' => 'required_if:payment_method,airtel_money|nullable|string',
            'payment_reference' => 'nullable|string',
            'pop_file' => 'required_if:payment_method,bank_transfer|nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'coupon_code' => 'nullable|string|max:64',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pop_file.required_if' => 'Attach a screenshot or PDF of the transfer so we can match it.',
        ];
    }
}
