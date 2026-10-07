<?php

namespace App\Http\Requests;

use App\Enums\TemplatePreset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTemplatePresetOwnerRequest extends FormRequest
{
    /**
     * The admin middleware already gates the route to super admins.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A null company releases the preset to everyone. The key has to be sent
     * all the same, so a malformed request cannot release one by accident.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['present', 'nullable', 'integer', Rule::exists('companies', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.present' => 'Choose a company, or make the design available to everyone.',
            'company_id.exists' => 'That company no longer exists.',
        ];
    }

    /**
     * Every template that loses access to its preset prints on the fallback,
     * so the fallback has to stay open to everyone.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('preset') === TemplatePreset::fallback() && $this->filled('company_id')) {
                    $validator->errors()->add(
                        'company_id',
                        TemplatePreset::fallback()->label().' is what every other design falls back to, so it stays available to everyone.',
                    );
                }
            },
        ];
    }
}
