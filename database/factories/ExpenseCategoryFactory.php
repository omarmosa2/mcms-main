<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->unique()->randomElement(['Rent', 'Utilities', 'Supplies', 'Equipment', 'Maintenance', 'Marketing', 'Insurance', 'Legal', 'Training', 'Other']),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
