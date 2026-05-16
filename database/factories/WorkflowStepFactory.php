<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'workflow_id' => Workflow::factory(),
            'step_order' => fake()->numberBetween(1, 5),
            'approver_role' => fake()->randomElement(['admin', 'clinic_admin', 'accountant']),
            'action_required' => 'approve',
        ];
    }
}
