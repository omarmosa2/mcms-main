<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalaryPayment>
 */
class EmployeeSalaryPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountDue = fake()->randomFloat(2, 500, 3000);

        return [
            'clinic_id' => Clinic::factory(),
            'employee_id' => function (array $attributes): int {
                return Employee::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'paid_by' => function (array $attributes): int {
                return User::factory()->create([
                    'clinic_id' => $attributes['clinic_id'],
                ])->id;
            },
            'period_month' => now()->format('Y-m'),
            'amount_due' => $amountDue,
            'amount_paid' => fake()->randomFloat(2, 1, $amountDue),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer']),
            'paid_at' => now()->toDateString(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
