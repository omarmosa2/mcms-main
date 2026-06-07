<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorMonthlyDue>
 */
class DoctorMonthlyDueFactory extends Factory
{
    protected $model = DoctorMonthlyDue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dueAmount = fake()->randomFloat(2, 500, 5000);

        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => function (array $attributes): int {
                return DoctorProfile::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'salary_month' => now()->format('Y-m'),
            'payment_type' => DoctorProfile::COMPENSATION_MONTHLY,
            'percentage' => null,
            'fixed_weekly_amount' => null,
            'fixed_monthly_amount' => $dueAmount,
            'visits_total_amount' => 0,
            'deductions_amount' => 0,
            'due_amount' => $dueAmount,
            'paid_amount' => 0,
            'remaining_amount' => $dueAmount,
            'status' => DoctorMonthlyDue::STATUS_UNPAID,
        ];
    }

    public function partiallyPaid(): static
    {
        return $this->afterCreating(function (DoctorMonthlyDue $due) {
            $paidAmount = $due->due_amount * 0.5;
            $due->update([
                'paid_amount' => $paidAmount,
                'remaining_amount' => $due->due_amount - $paidAmount,
                'status' => DoctorMonthlyDue::STATUS_PARTIALLY_PAID,
            ]);
        });
    }

    public function paid(): static
    {
        return $this->afterCreating(function (DoctorMonthlyDue $due) {
            $due->update([
                'paid_amount' => $due->due_amount,
                'remaining_amount' => 0,
                'status' => DoctorMonthlyDue::STATUS_PAID,
            ]);
        });
    }
}
