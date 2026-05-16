<?php

namespace Database\Factories;

use App\Models\Cashbox;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cashbox>
 */
class CashboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();
        $opening = fake()->randomFloat(2, 100, 1000);
        $income = fake()->randomFloat(2, 0, 2000);
        $expenses = fake()->randomFloat(2, 0, 500);

        return [
            'clinic_id' => $clinic,
            'opening_balance' => $opening,
            'total_income' => $income,
            'total_expenses' => $expenses,
            'closing_balance' => $opening + $income - $expenses,
            'box_date' => fake()->date(),
            'status' => 'open',
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'closed_by' => null,
            'closed_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Cashbox::STATUS_CLOSED,
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ]);
    }
}
