<?php

namespace App\Services\Workflows;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    public function trigger(int $clinicId, string $entityType, int $entityId): ?WorkflowInstance
    {
        $workflow = Workflow::query()
            ->forClinic($clinicId)
            ->active()
            ->forEntityType($entityType)
            ->with('steps')
            ->first();

        if ($workflow === null || $workflow->steps->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($clinicId, $workflow, $entityType, $entityId): WorkflowInstance {
            $instance = WorkflowInstance::query()->create([
                'clinic_id' => $clinicId,
                'workflow_id' => $workflow->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => 'in_progress',
                'current_step' => 1,
            ]);

            $firstStep = $workflow->steps->first();

            WorkflowApproval::query()->create([
                'clinic_id' => $clinicId,
                'instance_id' => $instance->id,
                'step_id' => $firstStep->id,
                'status' => 'pending',
            ]);

            return $instance;
        });
    }

    public function approve(int $approvalId, int $userId, ?string $comments = null): WorkflowApproval
    {
        return DB::transaction(function () use ($approvalId, $userId, $comments): WorkflowApproval {
            $approval = WorkflowApproval::query()
                ->with(['instance.workflow.steps'])
                ->findOrFail($approvalId);

            if ($approval->status !== 'pending') {
                abort(422, 'This approval has already been decided.');
            }

            $approval->status = 'approved';
            $approval->approver_id = $userId;
            $approval->comments = $comments;
            $approval->decided_at = now();
            $approval->save();

            $instance = $approval->instance;
            $workflow = $instance->workflow;
            $nextStepOrder = $instance->current_step + 1;

            $nextStep = $workflow->steps->firstWhere('step_order', $nextStepOrder);

            if ($nextStep === null) {
                $instance->status = 'approved';
                $instance->completed_at = now();
                $instance->save();
            } else {
                $instance->current_step = $nextStepOrder;
                $instance->save();

                WorkflowApproval::query()->create([
                    'clinic_id' => $instance->clinic_id,
                    'instance_id' => $instance->id,
                    'step_id' => $nextStep->id,
                    'status' => 'pending',
                ]);
            }

            return $approval;
        });
    }

    public function reject(int $approvalId, int $userId, ?string $comments = null): WorkflowApproval
    {
        return DB::transaction(function () use ($approvalId, $userId, $comments): WorkflowApproval {
            $approval = WorkflowApproval::query()->findOrFail($approvalId);

            if ($approval->status !== 'pending') {
                abort(422, 'This approval has already been decided.');
            }

            $approval->status = 'rejected';
            $approval->approver_id = $userId;
            $approval->comments = $comments;
            $approval->decided_at = now();
            $approval->save();

            $instance = $approval->instance;
            $instance->status = 'rejected';
            $instance->completed_at = now();
            $instance->save();

            return $approval;
        });
    }

    public function isApproved(string $entityType, int $entityId): bool
    {
        $instance = WorkflowInstance::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->approved()
            ->first();

        return $instance !== null;
    }

    public function getPendingApprovals(int $userId): array
    {
        $user = User::query()->with('roles')->find($userId);

        if ($user === null) {
            return [];
        }

        $roleNames = $user->roles->pluck('name')->toArray();

        $approvals = WorkflowApproval::query()
            ->with(['instance.entity', 'step'])
            ->pending()
            ->whereHas('step', function ($query) use ($roleNames): void {
                $query->whereIn('approver_role', $roleNames);
            })
            ->orderBy('created_at')
            ->get();

        return $approvals->toArray();
    }

    public function getInstanceForEntity(string $entityType, int $entityId): ?WorkflowInstance
    {
        return WorkflowInstance::query()
            ->with(['workflow.steps', 'approvals.step'])
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->first();
    }
}
