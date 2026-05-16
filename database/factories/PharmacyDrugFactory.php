<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PharmacyDrug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PharmacyDrug>
 */
class PharmacyDrugFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'trade_name' => fake()->words(2, true),
            'generic_name' => fake()->words(2, true),
            'dosage_form' => fake()->randomElement(['tablet', 'capsule', 'syrup', 'injection']),
            'strength' => fake()->randomElement(['100mg', '250mg', '500mg', '1g']),
            'unit_price' => fake()->numberBetween(500, 5000),
            'min_stock_level' => fake()->numberBetween(10, 50),
            'current_stock' => fake()->numberBetween(0, 200),
            'is_active' => true,
            'expires_at' => fake()->optional()->dateTimeBetween('+6 months', '+2 years'),
        ];
    }
}
