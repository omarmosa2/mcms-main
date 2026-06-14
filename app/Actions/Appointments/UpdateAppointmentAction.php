<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use App\Services\Cache\CacheService;
use App\Services\ClinicWorkingHoursService;
use App\Services\DoctorScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAppointmentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private DoctorScheduleService $doctorScheduleService,
        private ClinicWorkingHoursService $clinicWorkingHoursService,
        private CacheService $cacheService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $appointmentId, int $userId, array $payload): Appointment
    {
        $appointment = Appointment::query()
            ->forClinic($clinicId)
            ->findOrFail($appointmentId);

        if (in_array($appointment->status, Appointment::TERMINAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تعديل المواعيد النهائية.',
            ]);
        }

        if (array_key_exists('patient_id', $payload)) {
            $this->ensurePatientBelongsToClinic($clinicId, (int) $payload['patient_id']);
        }

        if (array_key_exists('doctor_id', $payload)) {
            $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['doctor_id']);
        }

        if (array_key_exists('scheduled_for', $payload) || array_key_exists('duration_minutes', $payload) || array_key_exists('doctor_id', $payload)) {
            $newScheduledFor = $payload['scheduled_for'] ?? $appointment->scheduled_for;
            $this->ensureScheduledForToday($newScheduledFor);
            $this->checkAppointmentConflicts(
                $clinicId,
                $appointmentId,
                [
                    'scheduled_for' => $payload['scheduled_for'] ?? $appointment->scheduled_for,
                    'duration_minutes' => $payload['duration_minutes'] ?? $appointment->duration_minutes,
                    'patient_id' => $payload['patient_id'] ?? $appointment->patient_id,
                    'doctor_id' => $payload['doctor_id'] ?? $appointment->doctor_id,
                ],
            );
            $this->checkClinicWorkingHours(
                $clinicId,
                [
                    'scheduled_for' => $payload['scheduled_for'] ?? $appointment->scheduled_for,
                    'duration_minutes' => $payload['duration_minutes'] ?? $appointment->duration_minutes,
                ],
            );
            $this->checkDoctorSchedule(
                $clinicId,
                [
                    'doctor_id' => $payload['doctor_id'] ?? $appointment->doctor_id,
                    'scheduled_for' => $payload['scheduled_for'] ?? $appointment->scheduled_for,
                    'duration_minutes' => $payload['duration_minutes'] ?? $appointment->duration_minutes,
                ],
            );
        }

        $oldValues = $appointment->only([
            'patient_id',
            'doctor_id',
            'appointment_number',
            'scheduled_for',
            'duration_minutes',
            'notes',
            'status',
        ]);

        $appointment->fill($payload);
        $appointment->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'appointments.update',
            auditable: $appointment,
            oldValues: $oldValues,
            newValues: $appointment->only([
                'patient_id',
                'doctor_id',
                'appointment_number',
                'scheduled_for',
                'duration_minutes',
                'notes',
                'status',
            ]),
        );

        $this->cacheService->invalidateDashboardStats($clinicId);
        $this->cacheService->invalidateDropdowns($clinicId);

        return $appointment->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkAppointmentConflicts(int $clinicId, int $appointmentId, array $payload): void
    {
        $startTime = Carbon::parse($payload['scheduled_for']);
        $duration = (int) ($payload['duration_minutes'] ?? 30);
        $endTime = $startTime->copy()->addMinutes($duration);

        $endTimeExpression = $this->getEndTimeExpression();

        $patientConflict = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $payload['patient_id'])
            ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
            ->where('id', '!=', $appointmentId)
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
                ->where('id', '!=', $appointmentId)
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
    private function checkClinicWorkingHours(int $clinicId, array $payload): void
    {
        $doctorId = $payload['doctor_id'] ?? null;

        if ($doctorId === null) {
            return;
        }

        $doctorProfile = DoctorProfile::query()
            ->forClinic($clinicId)
            ->where('user_id', $doctorId)
            ->first();

        if ($doctorProfile === null || $doctorProfile->department_id === null) {
            return;
        }

        $isAvailable = $this->clinicWorkingHoursService->isAppointmentWithinWorkingHours(
            $doctorProfile->department_id,
            $payload['scheduled_for'],
            (int) ($payload['duration_minutes'] ?? 30),
        );

        if (! $isAvailable) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'الوقت المختار خارج دوام العيادة.',
            ]);
        }
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

    private function ensureScheduledForToday(mixed $scheduledFor): void
    {
        $scheduledDate = Carbon::parse($scheduledFor)->toDateString();
        $today = now()->toDateString();

        if ($scheduledDate !== $today) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'يمكن تعديل المواعيد لليوم الحالي فقط.',
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
