<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSubscriptionRequest extends FormRequest
{
    /**
     * The admin middleware already gates the route to super admins.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only active plans are assignable. Public visibility is deliberately not
     * required — admin assignment is the only way onto a complimentary plan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'status' => 'required|in:active,pending_payment,paused,cancelled,expired',
            'ends_at' => 'nullable|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.exists' => 'That plan does not exist or is no longer active.',
            'ends_at.date' => 'Enter a valid expiry date, or leave it empty so the plan never expires.',
        ];
    }
}
