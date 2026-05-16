<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowStep extends BaseModel
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'workflow_id' => 'integer',
            'step_order' => 'integer',
            'auto_approve_after_hours' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class, 'step_id');
    }
}
