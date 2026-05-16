<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorProfile;

class DeleteDoctorProfileAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $doctorProfileId,
        int $userId,
        ?int $doctorScopeUserId = null,
    ): void {
        $query = DoctorProfile::query()
            ->forClinic($clinicId);

        if ($doctorScopeUserId !== null) {
            $query->where('user_id', $doctorScopeUserId);
        }

        $doctorProfile = $query->findOrFail($doctorProfileId);

        $oldValues = $doctorProfile->only([
            'user_id',
            'department_id',
            'license_number',
            'specialty',
            'consultation_duration_minutes',
            'status',
            'work_schedule',
            'bio',
        ]);

        $doctorProfile->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'doctor_profiles.delete',
            auditable: $doctorProfile,
            oldValues: $oldValues,
        );
    }
}
