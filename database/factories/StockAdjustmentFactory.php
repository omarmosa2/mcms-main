<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PharmacyDrug;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'pharmacy_drug_id' => PharmacyDrug::factory(),
            'quantity_change' => fake()->numberBetween(-50, 100),
            'reason' => fake()->randomElement(['count_correction', 'damaged', 'expired', 'received', 'returned', 'other']),
            'adjusted_by' => User::factory(),
            'adjusted_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
