<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;

class ShowAppointmentAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $appointmentId, int $userId, ?int $doctorId = null): Appointment
    {
        $query = Appointment::query()
            ->forClinic($clinicId)
            ->with(['patient:id,clinic_id,first_name,last_name', 'doctor:id,clinic_id,name']);

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        $appointment = $query->findOrFail($appointmentId);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'appointments.show',
            auditable: $appointment,
        );

        return $appointment;
    }
}
