<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\GenerateNumberAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\DoctorScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAppointmentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private GenerateNumberAction $generateNumberAction,
        private DoctorScheduleService $doctorScheduleService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): Appointment
    {
        $this->ensurePatientBelongsToClinic($clinicId, (int) $payload['patient_id']);
        $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['doctor_id'] ?? null);
        $this->checkAppointmentConflicts($clinicId, $payload);
        $this->checkDoctorSchedule($clinicId, $payload);

        $appointmentNumber = $this->generateNumberAction->handle(
            $clinicId,
            GenerateNumberAction::ENTITY_APPOINTMENT,
            $payload['appointment_number'] ?? null,
        );

        $appointment = Appointment::query()->create([
            ...$payload,
            'appointment_number' => $appointmentNumber,
            'clinic_id' => $clinicId,
            'created_by' => $userId,
            'status' => Appointment::STATUS_SCHEDULED,
            'arrived_at' => null,
            'completed_at' => null,
            'canceled_at' => null,
            'cancel_reason' => null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'appointments.create',
            auditable: $appointment,
            newValues: $appointment->only([
                'clinic_id',
                'patient_id',
                'doctor_id',
                'appointment_number',
                'scheduled_for',
                'duration_minutes',
                'status',
            ]),
        );

        return $appointment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkAppointmentConflicts(int $clinicId, array $payload): void
    {
        $startTime = Carbon::parse($payload['scheduled_for']);
        $duration = (int) ($payload['duration_minutes'] ?? 30);
        $endTime = $startTime->copy()->addMinutes($duration);

        $endTimeExpression = $this->getEndTimeExpression();

        $patientConflict = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $payload['patient_id'])
            ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
            ->where(function ($query) use ($startTime, $endTime, $endTimeExpression): void {
                $query
                    ->whereRaw($endTimeExpression, [$startTime])
                    ->where('scheduled_for', '<', $endTime);
            })
            ->exists();

        if ($patientConflict) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'المريض لديه موعد آخر بنفس الوقت',
            ]);
        }

        if (! empty($payload['doctor_id'])) {
            $doctorConflict = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->where('doctor_id', $payload['doctor_id'])
                ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
                ->where(function ($query) use ($startTime, $endTime, $endTimeExpression): void {
                    $query
                        ->whereRaw($endTimeExpression, [$startTime])
                        ->where('scheduled_for', '<', $endTime);
                })
                ->exists();

            if ($doctorConflict) {
                throw ValidationException::withMessages([
                    'scheduled_for' => 'الطبيب لديه موعد آخر بنفس الوقت',
                ]);
            }
        }
    }

    private function getEndTimeExpression(): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql' => 'DATE_ADD(scheduled_for, INTERVAL duration_minutes MINUTE) > ?',
            'sqlite' => "datetime(scheduled_for, '+' || duration_minutes || ' minutes') > ?",
            'pgsql' => '(scheduled_for + (duration_minutes || \' minutes\')::interval) > ?',
            default => 'DATE_ADD(scheduled_for, INTERVAL duration_minutes MINUTE) > ?',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkDoctorSchedule(int $clinicId, array $payload): void
    {
        if (empty($payload['doctor_id'])) {
            return;
        }

        $isAvailable = $this->doctorScheduleService->isDoctorAvailable(
            $clinicId,
            (int) $payload['doctor_id'],
            $payload['scheduled_for'],
            $payload['duration_minutes'] ?? 30,
        );

        if (! $isAvailable) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'الوقت المختار خارج دوام الطبيب',
            ]);
        }
    }

    private function ensurePatientBelongsToClinic(int $clinicId, int $patientId): void
    {
        $patientExists = Patient::query()
            ->forClinic($clinicId)
            ->whereKey($patientId)
            ->exists();

        if (! $patientExists) {
            throw ValidationException::withMessages([
                'patient_id' => 'المريض المحدد غير متاح لهذه العيادة.',
            ]);
        }
    }

    private function ensureDoctorBelongsToClinicIfProvided(int $clinicId, mixed $doctorId): void
    {
        if ($doctorId === null) {
            return;
        }

        $doctorExists = User::query()
            ->where('clinic_id', $clinicId)
            ->whereKey((int) $doctorId)
            ->whereHas('roles', function ($query) use ($clinicId): void {
                $query
                    ->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->exists();

        if (! $doctorExists) {
            throw ValidationException::withMessages([
                'doctor_id' => 'الطبيب المحدد غير متاح لهذه العيادة.',
            ]);
        }
    }
}
