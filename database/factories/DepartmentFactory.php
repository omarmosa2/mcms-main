<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'name' => 'Department '.strtoupper(fake()->unique()->bothify('??-###')),
            'code' => fake()->boolean(80)
                ? strtoupper(fake()->unique()->bothify('DEP-###'))
                : null,
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
