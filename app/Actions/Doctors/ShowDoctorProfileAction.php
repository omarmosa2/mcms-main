<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorProfile;

class ShowDoctorProfileAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $doctorProfileId,
        int $userId,
        ?int $doctorScopeUserId = null,
    ): DoctorProfile {
        $query = DoctorProfile::query()
            ->forClinic($clinicId)
            ->with([
                'user:id,clinic_id,name,email,is_active',
                'user.doctorSchedules:id,clinic_id,doctor_id,day_of_week,start_time,end_time,is_available',
                'department:id,clinic_id,name,code,is_active',
            ]);

        if ($doctorScopeUserId !== null) {
            $query->where('user_id', $doctorScopeUserId);
        }

        $doctorProfile = $query->findOrFail($doctorProfileId);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'doctor_profiles.show',
            auditable: $doctorProfile,
        );

        return $doctorProfile;
    }
}
