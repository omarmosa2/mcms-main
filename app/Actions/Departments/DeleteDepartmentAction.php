<?php

namespace App\Actions\Departments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Department;
use Illuminate\Validation\ValidationException;

class DeleteDepartmentAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $departmentId, int $userId): void
    {
        $department = Department::query()
            ->forClinic($clinicId)
            ->withCount('doctorProfiles')
            ->findOrFail($departmentId);

        if ($department->doctor_profiles_count > 0) {
            throw ValidationException::withMessages([
                'department' => 'Department cannot be deleted while doctor profiles are assigned to it.',
            ]);
        }

        $oldValues = $department->only([
            'name',
            'code',
            'description',
            'is_active',
        ]);

        $department->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'departments.delete',
            auditable: $department,
            oldValues: $oldValues,
        );
    }
}
