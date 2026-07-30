<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 500000),
            'currency_code' => 'ZMW',
            'billing_period' => 'monthly',
            'max_companies' => fake()->numberBetween(1, 10),
            'max_invoices' => fake()->numberBetween(1, 100),
            'max_quotations' => fake()->numberBetween(1, 100),
            'max_invoice_templates' => fake()->numberBetween(1, 10),
            'max_quotation_templates' => fake()->numberBetween(1, 10),
            'can_upload_custom_template' => false,
            'is_active' => true,
            'is_public' => true,
            'is_popular' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * A plan only admins can hand out: never listed, never sold.
     */
    public function complimentary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => 0,
            'is_public' => false,
            'is_popular' => false,
        ]);
    }
}
