<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\LabTestTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabTestTemplate>
 */
class LabTestTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('LAB-???'),
            'category' => fake()->randomElement(['hematology', 'chemistry', 'microbiology', 'immunology']),
            'unit' => fake()->randomElement(['mg/dL', 'mmol/L', 'U/L', 'g/L', 'cells/uL']),
            'min_reference' => fake()->randomFloat(2, 0, 50),
            'max_reference' => fake()->randomFloat(2, 50, 200),
            'is_active' => true,
        ];
    }
}
