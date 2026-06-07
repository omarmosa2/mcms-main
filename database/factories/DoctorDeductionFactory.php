<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DoctorDeduction;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorDeduction>
 */
class DoctorDeductionFactory extends Factory
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
            'doctor_profile_id' => function (array $attributes): int {
                return DoctorProfile::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'created_by' => function (array $attributes): int {
                return User::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'deduction_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'type' => fake()->randomElement(['deduction', 'advance']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
