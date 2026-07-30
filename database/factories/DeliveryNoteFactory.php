<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNote>
 */
class DeliveryNoteFactory extends Factory
{
    protected $model = DeliveryNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'invoice_id' => Invoice::factory(),
            'number' => 'DN-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => fake()->date(),
            'currency_code' => 'ZMW',
            'status' => 'draft',
        ];
    }
}
