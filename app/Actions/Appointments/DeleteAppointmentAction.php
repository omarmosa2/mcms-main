<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Services\Cache\CacheService;
use Illuminate\Validation\ValidationException;

class DeleteAppointmentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private CacheService $cacheService,
    ) {}

    public function handle(int $clinicId, int $appointmentId, int $userId): void
    {
        $appointment = Appointment::query()
            ->forClinic($clinicId)
            ->findOrFail($appointmentId);

        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن حذف إلا المواعيد المجدولة.',
            ]);
        }

        $oldValues = $appointment->only([
            'patient_id',
            'appointment_number',
            'scheduled_for',
            'status',
        ]);

        $appointment->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'appointments.delete',
            auditable: $appointment,
            oldValues: $oldValues,
        );

        $this->cacheService->invalidateDashboardStats($clinicId);
        $this->cacheService->invalidateDropdowns($clinicId);
    }
}
