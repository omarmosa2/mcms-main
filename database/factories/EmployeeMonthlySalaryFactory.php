<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Employee;
use App\Models\EmployeeMonthlySalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeMonthlySalary>
 */
class EmployeeMonthlySalaryFactory extends Factory
{
    protected $model = EmployeeMonthlySalary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseSalary = fake()->randomFloat(2, 500, 3000);

        return [
            'clinic_id' => Clinic::factory(),
            'employee_id' => function (array $attributes): int {
                return Employee::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                    'base_salary' => $attributes['base_salary'],
                ])->id;
            },
            'salary_month' => now()->format('Y-m'),
            'base_salary' => $baseSalary,
            'due_amount' => $baseSalary,
            'paid_amount' => 0,
            'remaining_amount' => $baseSalary,
            'status' => EmployeeMonthlySalary::STATUS_UNPAID,
        ];
    }

    public function partiallyPaid(): static
    {
        return $this->afterCreating(function (EmployeeMonthlySalary $salary) {
            $paidAmount = $salary->due_amount * 0.5;
            $salary->update([
                'paid_amount' => $paidAmount,
                'remaining_amount' => $salary->due_amount - $paidAmount,
                'status' => EmployeeMonthlySalary::STATUS_PARTIALLY_PAID,
            ]);
        });
    }

    public function paid(): static
    {
        return $this->afterCreating(function (EmployeeMonthlySalary $salary) {
            $salary->update([
                'paid_amount' => $salary->due_amount,
                'remaining_amount' => 0,
                'status' => EmployeeMonthlySalary::STATUS_PAID,
            ]);
        });
    }
}
