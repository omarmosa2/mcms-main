<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();

        return [
            'clinic_id' => $clinic,
            'queue_entry_id' => null,
            'appointment_id' => null,
            'patient_id' => Patient::factory()->for($clinic),
            'doctor_id' => null,
            'visit_number' => strtoupper(fake()->unique()->bothify('VIS-#####')),
            'status' => Visit::STATUS_STARTED,
            'started_at' => now(),
            'in_progress_at' => null,
            'completed_at' => null,
            'chief_complaint' => fake()->optional()->sentence(),
            'clinical_notes' => fake()->optional()->paragraph(),
            'diagnosis_notes' => fake()->optional()->sentence(),
            'treatment_plan' => fake()->optional()->sentence(),
        ];
    }
}
