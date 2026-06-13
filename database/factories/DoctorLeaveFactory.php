<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Department;
use App\Models\DoctorLeave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorLeave>
 */
class DoctorLeaveFactory extends Factory
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
            'doctor_id' => User::factory()->for($clinic),
            'department_id' => Department::factory()->for($clinic),
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->optional()->sentence(),
            'status' => DoctorLeave::STATUS_ACTIVE,
        ];
    }

    public function hourly(string $startTime = '10:00', string $endTime = '12:00'): static
    {
        return $this->state(fn (): array => [
            'type' => DoctorLeave::TYPE_HOURLY,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }
}
