<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DrugBatch;
use App\Models\PharmacyDrug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DrugBatch>
 */
class DrugBatchFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 500);

        return [
            'clinic_id' => Clinic::factory(),
            'pharmacy_drug_id' => PharmacyDrug::factory(),
            'batch_number' => fake()->unique()->bothify('BATCH-#####'),
            'quantity' => $quantity,
            'initial_quantity' => $quantity,
            'expiry_date' => fake()->dateTimeBetween('+3 months', '+2 years'),
            'received_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiry_date' => fake()->dateTimeBetween('-6 months', '-1 day'),
            'quantity' => 0,
        ]);
    }

    public function nearExpiry(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiry_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }
}
