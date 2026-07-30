<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 500000);

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'amount' => $amount,
            'original_amount' => $amount,
            'discount_amount' => 0,
            'currency_code' => 'ZMW',
            'payment_method' => 'airtel_money',
            'payment_reference' => strtoupper(fake()->bothify('REF#####')),
            'status' => 'pending',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }
}
