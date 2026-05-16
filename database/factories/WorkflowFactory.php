<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->words(3, true),
            'entity_type' => fake()->randomElement(['expense', 'purchase_order', 'salary']),
            'trigger_event' => 'created',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
