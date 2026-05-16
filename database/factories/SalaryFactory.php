<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salary>
 */
class SalaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();
        $baseSalary = fake()->randomFloat(2, 1000, 5000);
        $allowances = fake()->randomFloat(2, 0, 500);
        $deductions = fake()->randomFloat(2, 0, 200);

        return [
            'clinic_id' => $clinic,
            'user_id' => User::factory()->for($clinic),
            'base_salary' => $baseSalary,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $baseSalary + $allowances - $deductions,
            'status' => 'draft',
            'period_month' => fake()->date('Y-m'),
            'paid_at' => null,
            'paid_by' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Salary::STATUS_PAID,
            'paid_at' => now(),
            'paid_by' => User::factory(),
        ]);
    }
}
