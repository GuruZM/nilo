<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyExchangeRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->current_company_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /**
             * A rate of zero or below is not a slow currency, it is a broken
             * one — it would make every converted figure infinite or negative.
             */
            'rate' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'code' => [
                'required', 'string', 'size:3',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.required' => 'Enter the rate you want to use.',
            'rate.numeric' => 'The rate must be a number.',
            'rate.gt' => 'The rate must be greater than zero.',
            'code.exists' => 'That currency is not one of your active currencies.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->route('code')),
        ]);
    }

    public function currencyCode(): string
    {
        return $this->validated('code');
    }

    public function rate(): float
    {
        return (float) $this->validated('rate');
    }
}
