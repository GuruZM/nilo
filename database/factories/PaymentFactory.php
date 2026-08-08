<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'payment_method' => 'mobile_money',
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

    /**
     * A payment mid-flight at DPO: the token exists, the outcome does not yet.
     */
    public function dpo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_method' => Payment::METHOD_DPO,
            'payment_reference' => null,
            'company_ref' => (string) Str::ulid(),
            'dpo_transaction_token' => strtoupper(fake()->bothify('????????-????-????')),
            'status' => 'pending',
            'gateway_status' => 'pending',
            'charged_amount' => $attributes['amount'],
            'charged_currency_code' => $attributes['currency_code'] ?? 'ZMW',
        ]);
    }
}
