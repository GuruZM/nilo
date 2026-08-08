<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedOn = fake()->dateTimeBetween('-6 months', 'now');
        $total = fake()->randomFloat(2, 100, 25000);

        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'number' => 'QUO-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => $issuedOn,
            'valid_until' => (clone $issuedOn)->modify('+30 days'),
            'currency_code' => 'ZMW',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => $total,
            'status' => 'draft',
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'accepted']);
    }

    /**
     * Quote a fixed amount, keeping the totals consistent.
     */
    public function worth(float $total): static
    {
        return $this->state(fn () => [
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
