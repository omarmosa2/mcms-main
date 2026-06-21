<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDoctorProfileAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private AssignUserRoleAction $assignUserRoleAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        int $clinicId,
        int $doctorProfileId,
        int $userId,
        array $payload,
        ?int $doctorScopeUserId = null,
    ): DoctorProfile {
        return DB::transaction(function () use (
            $clinicId,
            $doctorProfileId,
            $userId,
            $payload,
            $doctorScopeUserId
        ): DoctorProfile {
            $query = DoctorProfile::query()->withoutGlobalScope('clinic');

            if ($doctorScopeUserId !== null) {
                $query->where('user_id', $doctorScopeUserId);
            }

            $doctorProfile = $query->findOrFail($doctorProfileId);
            $doctorClinicId = (int) $doctorProfile->clinic_id;

            if (array_key_exists('user_id', $payload) && ! empty($payload['user_id'])) {
                $doctorUserId = (int) $payload['user_id'];
                $this->ensureDoctorScopeCanManageUser($doctorScopeUserId, $doctorUserId);
                $this->ensureDoctorBelongsToClinic($doctorClinicId, $doctorUserId);
            }

            $oldValues = $doctorProfile->only($this->auditedProfileFields());
            $oldUserId = (int) $doctorProfile->user_id;

            $doctorProfile->fill($this->normalizeProfilePayload($payload));
            $doctorProfile->save();

            $doctorUser = User::query()
                ->where('clinic_id', $doctorClinicId)
                ->findOrFail((int) $doctorProfile->user_id);

            $this->updateDoctorUser($doctorUser, $payload);
            $this->assignUserRoleAction->handle($doctorUser, 'doctor', $userId);

            if (array_key_exists('working_hours', $payload)) {
                $this->syncWorkingHours($doctorClinicId, (int) $doctorProfile->user_id, $payload['working_hours']);

                if ($oldUserId !== (int) $doctorProfile->user_id) {
                    $this->syncWorkingHours($doctorClinicId, $oldUserId, []);
                }
            }

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'doctor_profiles.update',
                auditable: $doctorProfile,
                oldValues: $oldValues,
                newValues: $doctorProfile->only($this->auditedProfileFields()),
            );

            return $doctorProfile->load([
                'user:id,clinic_id,name,email,is_active',
                'user.doctorSchedules:id,clinic_id,doctor_id,day_of_week,start_time,end_time,is_available',
                'clinic:id,name,code,is_active',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeProfilePayload(array $payload): array
    {
        $normalized = [];

        if (array_key_exists('user_id', $payload) && ! empty($payload['user_id'])) {
            $normalized['user_id'] = (int) $payload['user_id'];
        }

        if (array_key_exists('gender', $payload)) {
            $normalized['gender'] = (string) $payload['gender'];
        }

        if (array_key_exists('phone', $payload)) {
            $phone = trim((string) ($payload['phone'] ?? ''));
            $normalized['phone'] = $phone !== '' ? $phone : null;
        }

        if (array_key_exists('work_start_date', $payload)) {
            $normalized['work_start_date'] = $this->normalizeNullableDate($payload['work_start_date']);
        }

        if (array_key_exists('license_number', $payload)) {
            $licenseNumber = trim((string) ($payload['license_number'] ?? ''));
            $normalized['license_number'] = $licenseNumber !== '' ? mb_strtoupper($licenseNumber) : null;
        }

        if (array_key_exists('specialty', $payload)) {
            $normalized['specialty'] = trim((string) $payload['specialty']);
        }

        if (array_key_exists('consultation_duration_minutes', $payload)) {
            $normalized['consultation_duration_minutes'] = (int) ($payload['consultation_duration_minutes'] ?? 30);
        }

        if (array_key_exists('status', $payload)) {
            $normalized['status'] = (string) ($payload['status'] ?? DoctorProfile::STATUS_ACTIVE);
        }

        if (array_key_exists('compensation_type', $payload)) {
            $normalized['compensation_type'] = (string) $payload['compensation_type'];
        }

        if (array_key_exists('compensation_value', $payload)) {
            $normalized['compensation_value'] = (float) $payload['compensation_value'];
        }

        if (array_key_exists('bio', $payload)) {
            $bio = trim((string) ($payload['bio'] ?? ''));
            $normalized['bio'] = $bio !== '' ? $bio : null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateDoctorUser(User $doctorUser, array $payload): void
    {
        $updates = [];

        if (array_key_exists('name', $payload)) {
            $updates['name'] = trim((string) $payload['name']);
        }

        if (array_key_exists('username', $payload)) {
            $updates['email'] = mb_strtolower(trim((string) $payload['username']));
        }

        if (! empty($payload['password'])) {
            $updates['password'] = (string) $payload['password'];
        }

        if (array_key_exists('is_active', $payload)) {
            $updates['is_active'] = (bool) $payload['is_active'];
        }

        if ($updates !== []) {
            $doctorUser->forceFill($updates)->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $workingHours
     */
    private function syncWorkingHours(int $clinicId, int $doctorUserId, array $workingHours): void
    {
        DoctorSchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorUserId)
            ->delete();

        foreach ($workingHours as $day) {
            if (! filter_var($day['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            DoctorSchedule::query()->create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorUserId,
                'day_of_week' => WeekDay::toIndex($day['day_of_week']),
                'start_time' => (string) $day['start_time'],
                'end_time' => (string) $day['end_time'],
                'is_available' => true,
            ]);
        }
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
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

    /**
     * @return array<int, string>
     */
    private function auditedProfileFields(): array
    {
        return [
            'user_id',
            'clinic_id',
            'gender',
            'phone',
            'work_start_date',
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
