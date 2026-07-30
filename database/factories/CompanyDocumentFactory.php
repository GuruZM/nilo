<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyDocument>
 */
class CompanyDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::slug($this->faker->words(2, true)).'.pdf';

        return [
            'company_id' => Company::factory(),
            'name' => Str::title($this->faker->words(2, true)),
            'file_path' => 'company-documents/'.Str::random(40).'.pdf',
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(10_000, 2_000_000),
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_path' => 'company-documents/'.Str::random(40).'.jpg',
            'original_filename' => 'scan.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
