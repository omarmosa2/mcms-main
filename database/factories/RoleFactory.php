<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
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
            'name' => fake()->unique()->slug(),
            'guard_name' => 'web',
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
        ];
    }
}
