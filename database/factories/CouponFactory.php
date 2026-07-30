<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'description' => fake()->sentence(4),
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'currency_code' => null,
            'starts_at' => null,
            'expires_at' => null,
            'max_redemptions' => null,
            'once_per_user' => true,
            'is_active' => true,
        ];
    }

    public function percentage(float $percent): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => $percent,
            'currency_code' => null,
        ]);
    }

    public function fixed(float $amount, string $currencyCode = 'ZMW'): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => $amount,
            'currency_code' => $currencyCode,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * A coupon with nothing left to give. The counter is written after the
     * fact because it is deliberately not mass-assignable — only a claim moves
     * it in production.
     */
    public function exhausted(): static
    {
        return $this
            ->state(fn (array $attributes): array => ['max_redemptions' => 5])
            ->afterCreating(fn (Coupon $coupon) => $coupon->forceFill(['redemptions_count' => 5])->save());
    }
}
