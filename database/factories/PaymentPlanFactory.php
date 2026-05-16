<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentPlan>
 */
class PaymentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'installment_count' => fake()->randomElement([3, 6, 12, 24]),
            'frequency' => fake()->randomElement(['monthly', 'quarterly', 'weekly']),
            'min_amount' => fake()->randomElement([10000, 50000, 100000]),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
