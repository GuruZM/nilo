<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    /**
     * The admin middleware already gates the route to super admins.
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
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('plans', 'slug')],
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:999999999',
            'currency_code' => 'required|string|size:3',
            'billing_period' => 'required|in:monthly,yearly',
            'max_companies' => 'required|integer|min:-1',
            'max_invoices' => 'required|integer|min:-1',
            'max_quotations' => 'required|integer|min:-1',
            'max_purchase_orders' => 'required|integer|min:-1',
            'max_invoice_templates' => 'required|integer|min:-1',
            'max_quotation_templates' => 'required|integer|min:-1',
            'can_upload_custom_template' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'features' => 'array',
            // Blank repeater rows arrive as null via ConvertEmptyStringsToNull;
            // the controller drops them rather than rejecting the submission.
            'features.*' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique' => 'Another plan already uses this slug.',
            'max_companies.min' => 'Use -1 for unlimited, or any number from 0 upwards.',
            'max_invoices.min' => 'Use -1 for unlimited, or any number from 0 upwards.',
            'max_quotations.min' => 'Use -1 for unlimited, or any number from 0 upwards.',
            'max_purchase_orders.min' => 'Use -1 for unlimited, or any number from 0 upwards.',
            'max_invoice_templates.min' => 'Use -1 for unlimited, or any number from 0 upwards.',
            'max_quotation_templates.min' => 'Use -1 for unlimited, or any number from 0 upwards.',
        ];
    }
}
