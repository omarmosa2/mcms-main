<?php

namespace App\Actions\Departments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Department;

class ShowDepartmentAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $departmentId, int $userId): Department
    {
        $department = Department::query()
            ->forClinic($clinicId)
            ->withCount('doctorProfiles')
            ->with([
                'workingHours',
                'creator:id,clinic_id,name',
                'updater:id,clinic_id,name',
            ])
            ->findOrFail($departmentId);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'departments.show',
            auditable: $department,
        );

        return $department;
    }
}
