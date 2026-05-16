<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\InventoryReturn;
use App\Models\PharmacyDrug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReturn>
 */
class InventoryReturnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'pharmacy_drug_id' => PharmacyDrug::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'reason' => fake()->randomElement(['expired', 'damaged', 'wrong_order', 'quality_issue', 'other']),
            'returned_to_supplier' => fake()->boolean(),
            'returned_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
