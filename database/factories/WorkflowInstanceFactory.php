<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowInstance>
 */
class WorkflowInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'workflow_id' => Workflow::factory(),
            'entity_type' => 'expense',
            'entity_id' => fake()->numberBetween(1, 100),
            'status' => 'in_progress',
            'current_step' => 1,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'approved',
            'completed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'rejected',
            'completed_at' => now(),
        ]);
    }
}
