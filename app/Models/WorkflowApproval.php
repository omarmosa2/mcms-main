<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkflowApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowApproval extends BaseModel
{
    /** @use HasFactory<WorkflowApprovalFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'instance_id' => 'integer',
            'step_id' => 'integer',
            'approver_id' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForApprover($query, int $approverId)
    {
        return $query->where('approver_id', $approverId);
    }
}
