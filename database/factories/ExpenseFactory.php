<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clinic = Clinic::factory();

        return [
            'clinic_id' => $clinic,
            'user_id' => null,
            'category_id' => ExpenseCategory::factory()->for($clinic),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'expense_date' => fake()->date(),
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
