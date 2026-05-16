<?php

namespace Tests\Feature\Workflows;

use App\Models\Clinic;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_trigger_workflow(): void
    {
        $clinic = Clinic::factory()->create();
        $workflow = Workflow::factory()->for($clinic)->create([
            'entity_type' => 'expense',
        ]);

        WorkflowStep::factory()->for($clinic)->for($workflow)->create([
            'step_order' => 1,
            'approver_role' => 'admin',
        ]);

        $expense = Expense::factory()->for($clinic)->create();

        $engine = app(WorkflowEngine::class);
        $instance = $engine->trigger($clinic->id, 'expense', $expense->id);

        $this->assertNotNull($instance);
        $this->assertEquals('in_progress', $instance->status);
        $this->assertEquals(1, $instance->current_step);
        $this->assertDatabaseHas('workflow_approvals', [
            'instance_id' => $instance->id,
            'status' => 'pending',
        ]);
    }

    public function test_can_approve_workflow_step(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();

        $workflow = Workflow::factory()->for($clinic)->create(['entity_type' => 'expense']);
        $step = WorkflowStep::factory()->for($clinic)->for($workflow)->create(['step_order' => 1]);
        $instance = WorkflowInstance::factory()->for($clinic)->for($workflow)->create([
            'entity_type' => 'expense',
            'entity_id' => 1,
            'current_step' => 1,
        ]);

        $approval = WorkflowApproval::factory()->for($clinic)->create([
            'instance_id' => $instance->id,
            'step_id' => $step->id,
            'status' => 'pending',
        ]);

        $engine = app(WorkflowEngine::class);
        $result = $engine->approve($approval->id, $user->id, 'Looks good');

        $this->assertEquals('approved', $result->status);
        $this->assertNotNull($result->decided_at);
        $this->assertEquals('Looks good', $result->comments);

        $instance->refresh();
        $this->assertEquals('approved', $instance->status);
    }

    public function test_can_reject_workflow_step(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();

        $workflow = Workflow::factory()->for($clinic)->create(['entity_type' => 'expense']);
        $step = WorkflowStep::factory()->for($clinic)->for($workflow)->create(['step_order' => 1]);
        $instance = WorkflowInstance::factory()->for($clinic)->for($workflow)->create([
            'entity_type' => 'expense',
            'entity_id' => 1,
            'current_step' => 1,
        ]);

        $approval = WorkflowApproval::factory()->for($clinic)->create([
            'instance_id' => $instance->id,
            'step_id' => $step->id,
            'status' => 'pending',
        ]);

        $engine = app(WorkflowEngine::class);
        $result = $engine->reject($approval->id, $user->id, 'Not approved');

        $this->assertEquals('rejected', $result->status);
        $this->assertNotNull($result->decided_at);

        $instance->refresh();
        $this->assertEquals('rejected', $instance->status);
    }

    public function test_workflow_advances_to_next_step(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();

        $workflow = Workflow::factory()->for($clinic)->create(['entity_type' => 'expense']);
        $step1 = WorkflowStep::factory()->for($clinic)->for($workflow)->create(['step_order' => 1]);
        $step2 = WorkflowStep::factory()->for($clinic)->for($workflow)->create(['step_order' => 2]);

        $instance = WorkflowInstance::factory()->for($clinic)->for($workflow)->create([
            'entity_type' => 'expense',
            'entity_id' => 1,
            'current_step' => 1,
        ]);

        $approval = WorkflowApproval::factory()->for($clinic)->create([
            'instance_id' => $instance->id,
            'step_id' => $step1->id,
            'status' => 'pending',
        ]);

        $engine = app(WorkflowEngine::class);
        $engine->approve($approval->id, $user->id);

        $instance->refresh();
        $this->assertEquals(2, $instance->current_step);
        $this->assertEquals('in_progress', $instance->status);

        $this->assertDatabaseHas('workflow_approvals', [
            'instance_id' => $instance->id,
            'step_id' => $step2->id,
            'status' => 'pending',
        ]);
    }

    public function test_is_approved_returns_true_for_approved_entity(): void
    {
        $clinic = Clinic::factory()->create();
        $workflow = Workflow::factory()->for($clinic)->create(['entity_type' => 'expense']);
        WorkflowInstance::factory()->for($clinic)->for($workflow)->approved()->create([
            'entity_type' => 'expense',
            'entity_id' => 42,
        ]);

        $engine = app(WorkflowEngine::class);
        $this->assertTrue($engine->isApproved('expense', 42));
        $this->assertFalse($engine->isApproved('expense', 99));
    }

    public function test_cannot_approve_already_decided_approval(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();

        $approval = WorkflowApproval::factory()->for($clinic)->approved()->create();

        $engine = app(WorkflowEngine::class);

        $this->expectException(HttpException::class);
        $engine->approve($approval->id, $user->id);
    }

    public function test_workflow_is_scoped_to_clinic(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        Workflow::factory()->for($clinic1)->create(['name' => 'Clinic 1 Workflow']);
        Workflow::factory()->for($clinic2)->create(['name' => 'Clinic 2 Workflow']);

        $workflows = Workflow::query()->forClinic($clinic1->id)->get();
        $this->assertCount(1, $workflows);
        $this->assertEquals('Clinic 1 Workflow', $workflows->first()->name);
    }
}
