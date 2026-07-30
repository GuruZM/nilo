<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'client_id' => Client::factory(),
            'invoice_id' => Invoice::factory(),
            'number' => 'CRN-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => fake()->date(),
            'currency_code' => 'ZMW',
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'draft',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => 'issued']);
    }

    /**
     * A credit against a specific invoice, matching its company and client.
     */
    public function forInvoice(Invoice $invoice, float $total): static
    {
        return $this->state(fn () => [
            'company_id' => $invoice->company_id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'currency_code' => $invoice->currency_code,
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
