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
        bool $allClinics = false,
    ): DoctorProfile {
        $query = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->with([
                'user:id,clinic_id,name,email,is_active',
                'schedules:id,clinic_id,doctor_id,day_of_week,start_time,end_time,is_available',
                'clinic:id,name,code,is_active',
                'clinic.workingHours:id,clinic_id,day_of_week,is_active,start_time,end_time',
            ]);

        if ($doctorScopeUserId !== null) {
            $query->where('user_id', $doctorScopeUserId);
        }

        if (! $allClinics) {
            $query->where('clinic_id', $clinicId);
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
