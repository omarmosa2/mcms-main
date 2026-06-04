<?php

namespace App\Actions\Departments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Department;
use App\Services\ClinicWorkingHoursService;
use Illuminate\Support\Facades\DB;

class UpdateDepartmentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private ClinicWorkingHoursService $clinicWorkingHoursService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $departmentId, int $userId, array $payload): Department
    {
        return DB::transaction(function () use ($clinicId, $departmentId, $userId, $payload): Department {
            $workingHours = $payload['working_hours'] ?? null;
            unset($payload['working_hours']);

            $department = Department::query()
                ->forClinic($clinicId)
                ->findOrFail($departmentId);

            $oldValues = $department->only([
                'name',
                'code',
                'description',
                'is_active',
            ]);

            $department->fill([
                ...$this->normalizePayload($department, $payload),
                'updated_by' => $userId,
            ]);
            $department->save();

            if (is_array($workingHours)) {
                $this->clinicWorkingHoursService->replaceForClinic($clinicId, $workingHours);
            }

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'departments.update',
                auditable: $department,
                oldValues: $oldValues,
                newValues: $department->only([
                    'name',
                    'code',
                    'description',
                    'is_active',
                ]),
            );

            return $department->loadCount('doctorProfiles')->load([
                'clinic.workingHours',
                'creator:id,clinic_id,name',
                'updater:id,clinic_id,name',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(Department $department, array $payload): array
    {
        $normalized = [];

        if (array_key_exists('name', $payload)) {
            $normalized['name'] = trim((string) $payload['name']);
        }

        if (array_key_exists('code', $payload)) {
            $code = trim((string) ($payload['code'] ?? ''));
            $normalized['code'] = $code !== '' ? mb_strtoupper($code) : null;
        }

        if (array_key_exists('description', $payload)) {
            $description = trim((string) ($payload['description'] ?? ''));
            $normalized['description'] = $description !== '' ? $description : null;
        }

        if (array_key_exists('is_active', $payload)) {
            $normalized['is_active'] = (bool) $payload['is_active'];
        } else {
            $normalized['is_active'] = (bool) $department->is_active;
        }

        return $normalized;
    }
}
