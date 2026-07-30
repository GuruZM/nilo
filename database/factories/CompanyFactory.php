<?php

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => CompanyType::Services,
            'currency_code' => 'ZMW',
        ];
    }

    public function productsBusiness(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CompanyType::Products,
        ]);
    }
}
