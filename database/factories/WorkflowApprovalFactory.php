<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\WorkflowApproval;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowApproval>
 */
class WorkflowApprovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'instance_id' => WorkflowInstance::factory(),
            'step_id' => WorkflowStep::factory(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'approved',
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'rejected',
            'decided_at' => now(),
        ]);
    }
}
