<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DoctorPayment;
use App\Models\DoctorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorPayment>
 */
class DoctorPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();
        $doctor = DoctorProfile::factory()->for($clinic);
        $periodStart = now()->startOfMonth()->toDateString();

        return [
            'clinic_id' => $clinic,
            'doctor_id' => $doctor,
            'payment_id' => null,
            'paid_by' => null,
            'payment_type' => DoctorPayment::TYPE_MONTHLY,
            'period_start' => $periodStart,
            'period_end' => now()->endOfMonth()->toDateString(),
            'dedupe_key' => 'monthly:'.$periodStart,
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'payment_method' => 'cash',
            'paid_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
