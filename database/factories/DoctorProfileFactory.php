<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorProfile>
 */
class DoctorProfileFactory extends Factory
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
            'user_id' => function (array $attributes): int {
                return User::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'department_id' => function (array $attributes): int {
                return Department::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'license_number' => fake()->boolean(85)
                ? strtoupper(fake()->unique()->bothify('LIC-########'))
                : null,
            'specialty' => fake()->randomElement([
                'Family Medicine',
                'Internal Medicine',
                'Pediatrics',
                'Dermatology',
                'Cardiology',
                'Orthopedics',
            ]),
            'consultation_duration_minutes' => fake()->randomElement([15, 20, 30, 45, 60]),
            'status' => fake()->randomElement([
                DoctorProfile::STATUS_ACTIVE,
                DoctorProfile::STATUS_ACTIVE,
                DoctorProfile::STATUS_ON_LEAVE,
                DoctorProfile::STATUS_INACTIVE,
            ]),
            'work_schedule' => [
                'sunday' => ['09:00-13:00', '17:00-20:00'],
                'monday' => ['09:00-13:00', '17:00-20:00'],
                'tuesday' => ['09:00-13:00'],
                'wednesday' => ['09:00-13:00', '17:00-20:00'],
                'thursday' => ['09:00-13:00'],
            ],
            'bio' => fake()->optional()->sentence(),
        ];
    }
}
