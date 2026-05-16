<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Installment;
use App\Models\PaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'payment_plan_id' => PaymentPlan::factory(),
            'installment_number' => fake()->numberBetween(1, 12),
            'amount' => fake()->numberBetween(1000, 50000),
            'due_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'status' => 'pending',
            'paid_amount' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
            'paid_amount' => $attributes['amount'],
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
            'due_date' => fake()->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }
}
