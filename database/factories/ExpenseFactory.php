<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
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
            'created_by' => null,
            'updated_by' => null,
            'category_id' => ExpenseCategory::factory()->for($clinic),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'expense_date' => fake()->date(),
            'payment_method' => fake()->randomElement(['cash', 'transfer', 'card', 'other']),
            'status' => 'pending',
            'paid_to' => fake()->optional()->name(),
            'reference_number' => fake()->optional()->numerify('REF-#####'),
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_PAID,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_PENDING,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_CANCELLED,
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'clinic_id' => null,
        ]);
    }
}
