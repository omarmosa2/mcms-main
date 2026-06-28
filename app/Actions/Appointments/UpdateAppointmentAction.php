<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\Patient;
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
     * @param  int|null  $userClinicId  العيادة الأم للمستخدم (للتحقق من ملكية المريض)
     */
    public function handle(int $clinicId, int $appointmentId, int $userId, array $payload, ?int $userClinicId = null): Appointment
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
            // المريض يُتحقق منه بـ clinic_id الخاص بالمستخدم (العيادة الأم)
            $patientClinicId = $userClinicId ?? $clinicId;
            $this->ensurePatientBelongsToClinic($patientClinicId, (int) $payload['patient_id']);
        }

        if (array_key_exists('doctor_id', $payload)) {
            $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['doctor_id']);
        }

        if (array_key_exists('scheduled_for', $payload) || array_key_exists('duration_minutes', $payload) || array_key_exists('doctor_id', $payload)) {
            $newScheduledFor = $payload['scheduled_for'] ?? $appointment->scheduled_for;
            $this->ensureScheduledIsNotInThePast($newScheduledFor);
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
            'appointment_type',
            'cost',
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
                'appointment_type',
                'cost',
                'notes',
                'status',
            ]),
        );

        $this->syncRelatedInvoice($clinicId, $appointment, $oldValues);
        $this->syncDoctorEntitlement($clinicId, $appointment, $oldValues);

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
        $isAvailable = $this->clinicWorkingHoursService->isAppointmentWithinWorkingHours(
            $clinicId,
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

    private function ensureScheduledIsNotInThePast(mixed $scheduledFor): void
    {
        $scheduledAt = Carbon::parse($scheduledFor);
        $scheduledDate = $scheduledAt->toDateString();
        $now = now();
        $today = $now->toDateString();

        if ($scheduledDate < $today) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'يمكن تعديل المواعيد لليوم الحالي فقط.',
            ]);
        }

        if ($scheduledDate === $today && $scheduledAt->lte($now)) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'الوقت المختار قد مضى بالفعل.',
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

        $doctorExists = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('user_id', (int) $doctorId)
            ->where('is_active', true)
            ->exists();

        if (! $doctorExists) {
            throw ValidationException::withMessages([
                'doctor_id' => 'الطبيب المحدد غير متاح لهذه العيادة.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $oldValues
     */
    private function syncRelatedInvoice(int $clinicId, Appointment $appointment, array $oldValues): void
    {
        $invoice = Invoice::query()
            ->forClinic($clinicId)
            ->where('appointment_id', $appointment->id)
            ->whereNotIn('status', [Invoice::STATUS_VOID])
            ->first();

        if ($invoice === null) {
            return;
        }

        $costChanged = array_key_exists('cost', $oldValues) && (float) ($oldValues['cost'] ?? 0) !== (float) ($appointment->cost ?? 0);
        $patientChanged = array_key_exists('patient_id', $oldValues) && (int) ($oldValues['patient_id'] ?? 0) !== (int) $appointment->patient_id;

        if (! $costChanged && ! $patientChanged) {
            return;
        }

        if ($costChanged) {
            $newCost = (float) ($appointment->cost ?? 0);
            $paidAmount = (float) $invoice->paid_amount;
            $newBalance = max(0, round($newCost - $paidAmount, 2));

            $invoice->subtotal_amount = $newCost;
            $invoice->total_amount = $newCost;
            $invoice->balance_amount = $newBalance;
        }

        if ($patientChanged) {
            $invoice->patient_id = $appointment->patient_id;
        }

        $invoice->save();
    }

    /**
     * @param  array<string, mixed>  $oldValues
     */
    private function syncDoctorEntitlement(int $clinicId, Appointment $appointment, array $oldValues): void
    {
        $doctorChanged = array_key_exists('doctor_id', $oldValues) && (int) ($oldValues['doctor_id'] ?? 0) !== (int) ($appointment->doctor_id ?? 0);
        $costChanged = array_key_exists('cost', $oldValues) && (float) ($oldValues['cost'] ?? 0) !== (float) ($appointment->cost ?? 0);
        $dateChanged = array_key_exists('scheduled_for', $oldValues)
            && Carbon::parse($oldValues['scheduled_for'])->toDateString() !== $appointment->scheduled_for?->toDateString();

        if (! $doctorChanged && ! $costChanged && ! $dateChanged) {
            return;
        }

        DoctorAppointmentEntitlement::query()
            ->forClinic($clinicId)
            ->where('appointment_id', $appointment->id)
            ->delete();

        if ($appointment->doctor_id === null) {
            return;
        }

        $cost = (float) ($appointment->cost ?? 0);

        if ($cost <= 0) {
            return;
        }

        $doctorProfile = DoctorProfile::query()
            ->forClinic($clinicId)
            ->where('user_id', $appointment->doctor_id)
            ->first();

        if ($doctorProfile === null || $doctorProfile->compensation_type !== DoctorProfile::COMPENSATION_PERCENTAGE) {
            return;
        }

        $percentage = (float) ($doctorProfile->compensationAmount() ?? 0);

        if ($percentage <= 0) {
            return;
        }

        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinicId,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => $cost,
            'percentage' => $percentage,
            'entitlement_amount' => $cost * ($percentage / 100),
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => $percentage,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => $appointment->scheduled_for?->toDateString(),
        ]);
    }
}
