<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartDpoCheckoutRequest extends FormRequest
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
     * `currency` is only ever a whitelist token. The amount it applies to is
     * always recomputed server-side, never read off the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            'coupon_code' => 'nullable|string|max:64',
            'currency' => ['nullable', 'string', Rule::in(config('services.dpo.allowed_currencies', ['ZMW']))],
        ];
    }
}
