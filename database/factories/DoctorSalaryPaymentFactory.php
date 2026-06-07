<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\DoctorSalaryPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorSalaryPayment>
 */
class DoctorSalaryPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountDue = fake()->randomFloat(2, 500, 5000);

        return [
            'clinic_id' => Clinic::factory(),
            'doctor_profile_id' => function (array $attributes): int {
                return DoctorProfile::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'paid_by' => function (array $attributes): int {
                return User::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'amount_due' => $amountDue,
            'amount_paid' => fake()->randomFloat(2, 1, $amountDue),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer']),
            'paid_at' => now()->toDateString(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
