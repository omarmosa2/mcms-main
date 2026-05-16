<?php

namespace App\Actions\Departments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

class CreateDepartmentAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): Department
    {
        return DB::transaction(function () use ($clinicId, $userId, $payload): Department {
            $department = Department::query()->create([
                ...$this->normalizePayload($payload),
                'clinic_id' => $clinicId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'departments.create',
                auditable: $department,
                newValues: $department->only([
                    'name',
                    'code',
                    'description',
                    'is_active',
                ]),
            );

            return $department->loadCount('doctorProfiles')->load([
                'creator:id,clinic_id,name',
                'updater:id,clinic_id,name',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        return [
            'name' => $name,
            'code' => $code !== '' ? mb_strtoupper($code) : null,
            'description' => $description !== '' ? $description : null,
            'is_active' => array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : true,
        ];
    }
}
