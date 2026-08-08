<?php

namespace App\Support;

use App\Models\DeliveryNote;
use App\Models\InvoicePayment;
use App\Models\PurchaseOrder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for creating documents, shared by the web controllers and
 * the mobile API.
 *
 * One copy so the two front doors cannot come to disagree about what a valid
 * invoice is — a phone accepting a payload the browser rejects, or the reverse,
 * would be a very quiet bug.
 *
 * Every foreign key is scoped to the acting company. That scoping is not a
 * nicety: without it a client or template id belonging to another tenant would
 * validate cleanly and be written onto this tenant's document.
 */
class DocumentRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function invoice(int $companyId): array
    {
        return [
            'client_id' => ['required', 'integer', self::clientRule($companyId)],
            'invoice_template_id' => ['required', 'integer', self::templateRule($companyId, 'invoice')],

            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],

            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],

            'currency_code' => ['required', 'string', 'size:3', self::currencyRule()],

            'has_delivery_note' => ['required', 'boolean'],

            'status' => ['required', 'string', Rule::in(['pending', 'paid'])],

            'is_recurring' => ['required', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_start_date' => ['nullable', 'date'],
            'recurrence_end_date' => ['nullable', 'date', 'after_or_equal:recurrence_start_date'],

            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'send_to_client' => ['nullable', 'boolean'],

            ...self::items(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function quotation(int $companyId): array
    {
        return [
            'client_id' => ['required', 'integer', self::clientRule($companyId)],
            'quotation_template_id' => ['required', 'integer', self::templateRule($companyId, 'quotation')],

            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],

            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],

            'currency_code' => ['required', 'string', 'size:3', self::currencyRule()],

            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'expired'])],

            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'quotation_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'send_to_client' => ['nullable', 'boolean'],

            ...self::items(),
        ];
    }

    /**
     * `currency_code` is absent on purpose. A credit note is denominated by the
     * invoice it credits, so there is no currency here for the client to
     * submit — validating one would imply otherwise. {@see \App\Services\InvoiceSettlement}
     * sums credit totals against the invoice total without conversion, so a
     * note in a different currency would subtract a foreign number from a local
     * balance and could wrongly settle the invoice.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function creditNote(int $companyId): array
    {
        return [
            'invoice_id' => ['required', 'integer', self::invoiceRule($companyId)],

            'issue_date' => ['required', 'date'],

            'status' => ['required', Rule::in(['draft', 'issued', 'void'])],
            'reason' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'credit_note_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            ...self::items(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function purchaseOrder(int $companyId): array
    {
        return [
            'supplier_id' => ['required', 'integer', self::supplierRule($companyId)],

            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],

            'issue_date' => ['required', 'date'],

            /** Same-day delivery is ordinary, so `after_or_equal` rather than `after`. */
            'expected_date' => ['nullable', 'date', 'after_or_equal:issue_date'],

            /**
             * A purchase order is denominated in whatever the supplier invoices
             * in, so the code comes from the client — but only one the
             * currencies table knows.
             */
            'currency_code' => ['required', 'string', 'size:3', self::currencyRule()],

            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],

            'delivery_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'purchase_order_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            ...self::items(),
        ];
    }

    /**
     * Money received against an invoice.
     *
     * `numeric` makes `max` a value comparison rather than a length one, so
     * this caps the payment at what is still owed. On a fully settled invoice
     * the cap is `max:0` and every payment is refused, which is correct.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function invoicePayment(float $balanceDue): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balanceDue],
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::in(InvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * On a fully settled invoice the cap is `max:0` and every payment is
     * refused, which is correct — this says so in words rather than quoting a
     * meaningless zero.
     *
     * @return array<string, string>
     */
    public static function invoicePaymentMessages(\App\Models\Invoice $invoice, float $balanceDue): array
    {
        return [
            'amount.max' => $balanceDue > 0
                ? 'That is more than the '.number_format($balanceDue, 2, '.', ',').' '
                    .$invoice->currency_code.' still outstanding on this invoice.'
                : 'Invoice '.$invoice->number.' is already settled in full.',
        ];
    }

    /**
     * Money received where no invoice was raised.
     *
     * No `max` on the amount, unlike {@see invoicePayment()}: there is no
     * balance to cap it against, and a receipt records what was handed over
     * rather than what was owed.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function standaloneReceipt(int $companyId): array
    {
        return [
            'client_id' => ['required', 'integer', self::clientRule($companyId)],

            'amount' => ['required', 'numeric', 'min:0.01'],

            /**
             * Taken from the request, not from a company default: cash received
             * in a currency the company does not usually bill in is exactly the
             * case this document exists for.
             */
            'currency_code' => ['required', 'string', 'size:3', self::currencyRule()],

            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::in(InvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function deliveryNoteUpdate(): array
    {
        return [
            'status' => ['required', Rule::in(DeliveryNote::STATUSES)],
            'delivery_date' => ['nullable', 'date'],
            'deliver_to' => ['nullable', 'string', 'max:190'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:190'],
            'received_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * The line items every priced document shares.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function items(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public static function clientRule(int $companyId): Exists
    {
        return Rule::exists('clients', 'id')->where('company_id', $companyId);
    }

    public static function supplierRule(int $companyId): Exists
    {
        return Rule::exists('suppliers', 'id')->where('company_id', $companyId);
    }

    public static function invoiceRule(int $companyId): Exists
    {
        return Rule::exists('invoices', 'id')->where('company_id', $companyId);
    }

    /**
     * Scoped to the company *and* the document type, so a template belonging to
     * another company — or to quotations rather than invoices — cannot be
     * submitted.
     */
    public static function templateRule(int $companyId, string $type): Exists
    {
        return Rule::exists('invoice_templates', 'id')
            ->where('company_id', $companyId)
            ->where('type', $type);
    }

    public static function currencyRule(): Exists
    {
        return Rule::exists('currencies', 'code');
    }
}
