<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateActiveCurrenciesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codes' => ['present', 'array'],
            'codes.*' => ['required', 'string', 'size:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codes.present' => 'Select which currencies should be active.',
            'codes.*.size' => 'Currency codes must be three letters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $codes = $this->input('codes');

        if (! is_array($codes)) {
            return;
        }

        $this->merge([
            'codes' => array_values(array_unique(array_map(
                fn ($code) => strtoupper((string) $code),
                $codes,
            ))),
        ]);
    }

    /**
     * Refuse to deactivate a currency the business is still relying on.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $blocked = $this->currenciesStillInUse();

                if ($blocked === []) {
                    return;
                }

                $validator->errors()->add('codes', sprintf(
                    'These currencies are still in use and cannot be deactivated: %s.',
                    implode(', ', $blocked),
                ));
            },
        ];
    }

    /**
     * The codes that should end up active, uppercased and de-duplicated.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->validated('codes');
    }

    /**
     * Currently-active codes this request would switch off, but which the user,
     * a company or an issued document still references.
     *
     * @return list<string>
     */
    protected function currenciesStillInUse(): array
    {
        $keeping = $this->input('codes', []);

        $losing = Currency::query()
            ->where('is_active', true)
            ->whereNotIn('code', $keeping)
            ->pluck('code')
            ->all();

        if ($losing === []) {
            return [];
        }

        $inUse = array_unique(array_merge(
            array_filter([strtoupper((string) $this->user()->current_currency_code)]),
            Company::query()->whereIn('currency_code', $losing)->pluck('currency_code')->all(),
            Invoice::query()->whereIn('currency_code', $losing)->pluck('currency_code')->all(),
            Quotation::query()->whereIn('currency_code', $losing)->pluck('currency_code')->all(),
        ));

        $blocked = array_values(array_intersect($losing, $inUse));

        sort($blocked);

        return $blocked;
    }
}
