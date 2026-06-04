<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDoctorProfileAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private AssignUserRoleAction $assignUserRoleAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload, ?int $doctorScopeUserId = null): DoctorProfile
    {
        return DB::transaction(function () use ($clinicId, $userId, $payload, $doctorScopeUserId): DoctorProfile {
            $doctorUser = $this->resolveDoctorUser($clinicId, $userId, $payload);
            $this->ensureDoctorScopeCanManageUser($doctorScopeUserId, (int) $doctorUser->id);
            $this->ensureDepartmentBelongsToClinicIfProvided($clinicId, $payload['department_id'] ?? null);

            $doctorProfile = DoctorProfile::query()->create([
                ...$this->normalizeProfilePayload($payload, (int) $doctorUser->id),
                'clinic_id' => $clinicId,
            ]);

            $this->syncWorkingHours($clinicId, (int) $doctorUser->id, $payload['working_hours'] ?? []);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'doctor_profiles.create',
                auditable: $doctorProfile,
                newValues: $doctorProfile->only($this->auditedProfileFields()),
            );

            return $doctorProfile->load([
                'user:id,clinic_id,name,email,is_active',
                'user.doctorSchedules:id,clinic_id,doctor_id,day_of_week,start_time,end_time,is_available',
                'department:id,clinic_id,name,code,is_active',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDoctorUser(int $clinicId, int $actingUserId, array $payload): User
    {
        if (! empty($payload['user_id'])) {
            $doctorUserId = (int) $payload['user_id'];
            $this->ensureDoctorBelongsToClinic($clinicId, $doctorUserId);

            return User::query()->where('clinic_id', $clinicId)->findOrFail($doctorUserId);
        }

        $doctorUser = User::query()->create([
            'clinic_id' => $clinicId,
            'name' => trim((string) $payload['name']),
            'email' => mb_strtolower(trim((string) $payload['username'])),
            'password' => (string) $payload['password'],
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->assignUserRoleAction->handle($doctorUser, 'doctor', $actingUserId);

        return $doctorUser;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeProfilePayload(array $payload, int $doctorUserId): array
    {
        $licenseNumber = trim((string) ($payload['license_number'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $bio = trim((string) ($payload['bio'] ?? ''));

        return [
            'user_id' => $doctorUserId,
            'department_id' => $this->normalizeNullableInteger($payload['department_id'] ?? null),
            'gender' => (string) $payload['gender'],
            'phone' => $phone !== '' ? $phone : null,
            'license_number' => $licenseNumber !== '' ? mb_strtoupper($licenseNumber) : null,
            'specialty' => trim((string) $payload['specialty']),
            'consultation_duration_minutes' => (int) ($payload['consultation_duration_minutes'] ?? 30),
            'status' => (string) ($payload['status'] ?? DoctorProfile::STATUS_ACTIVE),
            'compensation_type' => (string) $payload['compensation_type'],
            'compensation_value' => (float) $payload['compensation_value'],
            'work_schedule' => null,
            'bio' => $bio !== '' ? $bio : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workingHours
     */
    private function syncWorkingHours(int $clinicId, int $doctorUserId, array $workingHours): void
    {
        DoctorSchedule::query()
            ->withTrashed()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorUserId)
            ->forceDelete();

        foreach ($workingHours as $day) {
            if (! filter_var($day['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            DoctorSchedule::query()->create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorUserId,
                'day_of_week' => (int) $day['day_of_week'],
                'start_time' => (string) $day['start_time'],
                'end_time' => (string) $day['end_time'],
                'is_available' => true,
            ]);
        }
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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
                'department_id' => 'The selected clinic is not available.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function auditedProfileFields(): array
    {
        return [
            'user_id',
            'department_id',
            'gender',
            'phone',
            'license_number',
            'specialty',
            'consultation_duration_minutes',
            'status',
            'compensation_type',
            'compensation_value',
            'bio',
        ];
    }
}
