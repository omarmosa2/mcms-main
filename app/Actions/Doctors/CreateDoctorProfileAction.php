<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDoctorProfileAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload, ?int $doctorScopeUserId = null): DoctorProfile
    {
        return DB::transaction(function () use ($clinicId, $userId, $payload, $doctorScopeUserId): DoctorProfile {
            $doctorUserId = (int) $payload['user_id'];
            $this->ensureDoctorScopeCanManageUser($doctorScopeUserId, $doctorUserId);
            $this->ensureDoctorBelongsToClinic($clinicId, $doctorUserId);
            $this->ensureDepartmentBelongsToClinicIfProvided($clinicId, $payload['department_id'] ?? null);

            $doctorProfile = DoctorProfile::query()->create([
                ...$this->normalizePayload($payload),
                'clinic_id' => $clinicId,
            ]);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'doctor_profiles.create',
                auditable: $doctorProfile,
                newValues: $doctorProfile->only([
                    'user_id',
                    'department_id',
                    'license_number',
                    'specialty',
                    'consultation_duration_minutes',
                    'status',
                    'work_schedule',
                    'bio',
                ]),
            );

            return $doctorProfile->load([
                'user:id,clinic_id,name,email',
                'department:id,clinic_id,name,code,is_active',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $licenseNumber = trim((string) ($payload['license_number'] ?? ''));
        $specialty = trim((string) ($payload['specialty'] ?? ''));
        $bio = trim((string) ($payload['bio'] ?? ''));

        return [
            'user_id' => (int) $payload['user_id'],
            'department_id' => $this->normalizeNullableInteger($payload['department_id'] ?? null),
            'license_number' => $licenseNumber !== '' ? mb_strtoupper($licenseNumber) : null,
            'specialty' => $specialty,
            'consultation_duration_minutes' => (int) ($payload['consultation_duration_minutes'] ?? 30),
            'status' => (string) ($payload['status'] ?? DoctorProfile::STATUS_ACTIVE),
            'work_schedule' => $this->normalizeWorkSchedule($payload['work_schedule'] ?? null),
            'bio' => $bio !== '' ? $bio : null,
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeWorkSchedule(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value === [] ? null : $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded === [] ? null : $decoded;
            }
        }

        return null;
    }

    private function ensureDoctorScopeCanManageUser(?int $doctorScopeUserId, int $doctorUserId): void
    {
        if ($doctorScopeUserId === null) {
            return;
        }

        if ($doctorScopeUserId !== $doctorUserId) {
            throw ValidationException::withMessages([
                'user_id' => 'Doctors can only manage their own profile.',
            ]);
        }
    }

    private function ensureDoctorBelongsToClinic(int $clinicId, int $doctorUserId): void
    {
        $doctorExists = User::query()
            ->where('clinic_id', $clinicId)
            ->whereKey($doctorUserId)
            ->whereHas('roles', function (Builder $builder) use ($clinicId): void {
                $builder
                    ->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->exists();

        if (! $doctorExists) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user must be a doctor in this clinic.',
            ]);
        }
    }

    private function ensureDepartmentBelongsToClinicIfProvided(int $clinicId, mixed $departmentId): void
    {
        if ($departmentId === null || $departmentId === '') {
            return;
        }

        $departmentExists = Department::query()
            ->forClinic($clinicId)
            ->whereKey((int) $departmentId)
            ->exists();

        if (! $departmentExists) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department is not available for this clinic.',
            ]);
        }
    }
}
