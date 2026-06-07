<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DoctorDuePayment;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorDuePayment>
 */
class DoctorDuePaymentFactory extends Factory
{
    protected $model = DoctorDuePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();
        $salaryMonth = now()->format('Y-m');

        return [
            'clinic_id' => $clinic,
            'doctor_monthly_due_id' => DoctorMonthlyDue::factory([
                'clinic_id' => $clinic,
                'salary_month' => $salaryMonth,
            ]),
            'doctor_id' => function (array $attributes): int {
                $monthlyDue = DoctorMonthlyDue::find($attributes['doctor_monthly_due_id']);

                return $monthlyDue?->doctor_id ?? DoctorProfile::factory()->create(['clinic_id' => $attributes['clinic_id']])->id;
            },
            'paid_by' => function (array $attributes): int {
                return User::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'salary_month' => $salaryMonth,
            'amount' => fake()->randomFloat(2, 100, 2000),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer']),
            'payment_date' => now()->toDateString(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
