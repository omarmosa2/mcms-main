<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientCardVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientCardVisit>
 */
class PatientCardVisitFactory extends Factory
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
            'patient_id' => Patient::factory(),
            'doctor_id' => null,
            'department_id' => null,
            'visit_date' => fake()->date(),
            'visit_reason' => fake()->optional()->sentence(),
            'chief_complaint' => fake()->optional()->sentence(),
            'general_notes' => fake()->optional()->paragraph(),
            'new_symptoms' => fake()->optional()->sentence(),
            'medical_or_surgical_complaint' => fake()->optional()->sentence(),
            'diagnosis' => fake()->optional()->sentence(),
            'prescribed_treatment_or_referral' => fake()->optional()->sentence(),
            'signature' => fake()->optional()->name(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
