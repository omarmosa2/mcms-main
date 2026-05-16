<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'patient_id' => Patient::factory()->for($clinic),
            'doctor_id' => null,
            'created_by' => null,
            'appointment_number' => strtoupper(fake()->unique()->bothify('APT-#####')),
            'scheduled_for' => fake()->dateTimeBetween('+1 hour', '+30 days'),
            'duration_minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
            'arrived_at' => null,
            'completed_at' => null,
            'canceled_at' => null,
            'cancel_reason' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
