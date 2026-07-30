<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invoice_id' => Invoice::factory(),
            'recorded_by' => null,
            'receipt_number' => 'RCP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency_code' => 'ZMW',
            'paid_on' => fake()->date(),
            'method' => fake()->randomElement(InvoicePayment::METHODS),
            'reference' => null,
        ];
    }

    /**
     * Ties the payment to an existing invoice, matching its company and currency.
     */
    public function forInvoice(Invoice $invoice, ?float $amount = null): static
    {
        return $this->state(fn () => [
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'currency_code' => $invoice->currency_code,
            'amount' => $amount ?? (float) $invoice->total,
        ]);
    }
}
