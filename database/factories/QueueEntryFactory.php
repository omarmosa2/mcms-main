<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
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
            'appointment_id' => null,
            'patient_id' => Patient::factory()->for($clinic),
            'assigned_doctor_id' => null,
            'called_by' => null,
            'queue_date' => fake()->date(),
            'queue_number' => fake()->numberBetween(1, 200),
            'priority' => 0,
            'status' => QueueEntry::STATUS_WAITING,
            'checked_in_at' => now(),
            'called_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
