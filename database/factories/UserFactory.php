<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create the user with a free plan subscription.
     */
    public function withSubscription(string $planSlug = 'free'): static
    {
        return $this->afterCreating(function ($user) use ($planSlug) {
            $plan = Plan::firstOrCreate(
                ['slug' => $planSlug],
                [
                    'name' => ucfirst($planSlug),
                    'slug' => $planSlug,
                    'price' => 0,
                    'currency_code' => 'ZMW',
                    'billing_period' => 'monthly',
                    'max_companies' => -1,
                    'max_invoices' => -1,
                    'max_quotations' => -1,
                    'max_invoice_templates' => -1,
                    'max_quotation_templates' => -1,
                    'can_upload_custom_template' => true,
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'payment_method' => 'free',
            ]);
        });
    }
}
