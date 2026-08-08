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
            'payment_method' => 'required|in:mobile_money,bank_transfer',
            // Both routes are the customer sending money to one of Nilo's own
            // accounts, so the reference is the only thing that lets an admin
            // match what arrived to who is waiting on a plan.
            'payment_reference' => 'required|string|max:255',
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
            'payment_reference.required' => 'Enter the transaction ID or reference from your payment confirmation.',
            'pop_file.required_if' => 'Attach a screenshot or PDF of the transfer so we can match it.',
        ];
    }
}
