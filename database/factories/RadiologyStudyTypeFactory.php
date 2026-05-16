<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\RadiologyStudyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RadiologyStudyType>
 */
class RadiologyStudyTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('RAD-???'),
            'description' => fake()->sentence(),
            'requires_contrast' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
