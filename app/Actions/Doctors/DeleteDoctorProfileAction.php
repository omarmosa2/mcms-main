<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class DeleteDoctorProfileAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $doctorProfileId,
        int $userId,
        ?int $doctorScopeUserId = null,
    ): void {
        DB::transaction(function () use ($clinicId, $doctorProfileId, $userId, $doctorScopeUserId): void {
            $query = DoctorProfile::query()
                ->forClinic($clinicId)
                ->with('user:id,clinic_id,is_active');

            if ($doctorScopeUserId !== null) {
                $query->where('user_id', $doctorScopeUserId);
            }

            $doctorProfile = $query->findOrFail($doctorProfileId);
            $oldValues = $doctorProfile->only($this->auditedProfileFields());

            if ($this->hasOperationalHistory($clinicId, (int) $doctorProfile->user_id)) {
                $doctorProfile->forceFill(['status' => DoctorProfile::STATUS_INACTIVE])->save();
                $doctorProfile->user?->forceFill(['is_active' => false])->save();

                $this->logAuditAction->handle(
                    clinicId: $clinicId,
                    userId: $userId,
                    action: 'doctor_profiles.archive',
                    auditable: $doctorProfile,
                    oldValues: $oldValues,
                    newValues: $doctorProfile->only($this->auditedProfileFields()),
                );

                return;
            }

            $doctorProfile->delete();
            $doctorProfile->user?->forceFill(['is_active' => false])->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'doctor_profiles.delete',
                auditable: $doctorProfile,
                oldValues: $oldValues,
            );
        });
    }

    private function hasOperationalHistory(int $clinicId, int $doctorUserId): bool
    {
        return Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorUserId)
            ->exists()
            || Visit::query()
                ->where('clinic_id', $clinicId)
                ->where('doctor_id', $doctorUserId)
                ->exists()
            || Invoice::query()
                ->where('clinic_id', $clinicId)
                ->where('issued_by', $doctorUserId)
                ->exists()
            || Payment::query()
                ->where('clinic_id', $clinicId)
                ->where('received_by', $doctorUserId)
                ->exists();
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
