<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedOn = fake()->dateTimeBetween('-6 months', 'now');
        $total = fake()->randomFloat(2, 100, 25000);

        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'number' => 'INV-'.fake()->unique()->numberBetween(100000, 999999),
            'issue_date' => $issuedOn,
            'due_date' => (clone $issuedOn)->modify('+30 days'),
            'currency_code' => 'ZMW',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => $total,
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'paid']);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    /**
     * Issue the invoice in a specific currency.
     */
    public function in(string $currencyCode): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_code' => strtoupper($currencyCode),
        ]);
    }

    /**
     * Issue the invoice for a fixed amount, keeping the totals consistent.
     */
    public function worth(float $total): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
