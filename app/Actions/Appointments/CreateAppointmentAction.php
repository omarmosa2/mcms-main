<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\Billing\RecordPaymentAction;
use App\Actions\GenerateNumberAction;
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

class CreateAppointmentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private GenerateNumberAction $generateNumberAction,
        private DoctorScheduleService $doctorScheduleService,
        private ClinicWorkingHoursService $clinicWorkingHoursService,
        private CacheService $cacheService,
        private RecordPaymentAction $recordPaymentAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  int|null  $userClinicId  العيادة الأم للمستخدم (للتحقق من ملكية المريض)
     */
    public function handle(int $clinicId, int $userId, array $payload, ?int $userClinicId = null): Appointment
    {
        // المريض يُتحقق منه بـ clinic_id الخاص بالمستخدم (العيادة الأم)
        // وليس بالعيادة الفرعية المُختارة للحجز
        $patientClinicId = $userClinicId ?? $clinicId;
        $this->ensurePatientBelongsToClinic($patientClinicId, (int) $payload['patient_id']);
        $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['doctor_id'] ?? null);
        $this->ensureScheduledIsNotInThePast($payload['scheduled_for']);
        $this->checkAppointmentConflicts($clinicId, $payload);
        $this->checkClinicWorkingHours($clinicId, $payload);
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
                'appointment_type',
                'cost',
            ]),
        );

        $invoice = $this->createInvoiceForAppointmentIfNeeded($clinicId, $userId, $appointment);

        if ($invoice !== null) {
            $this->recordPaymentForInvoice($clinicId, $userId, $invoice);
        }

        $this->createDoctorEntitlementIfNeeded($clinicId, $appointment);

        $this->cacheService->invalidateDashboardStats($clinicId);
        $this->cacheService->invalidateDropdowns($clinicId);

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
                'scheduled_for' => 'يمكن حجز مواعيد لليوم الحالي فقط.',
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

    private function createInvoiceForAppointmentIfNeeded(int $clinicId, int $userId, Appointment $appointment): ?Invoice
    {
        $cost = (float) ($appointment->cost ?? 0);

        if ($cost <= 0) {
            return null;
        }

        $invoiceNumber = $this->generateInvoiceNumber($clinicId);

        $invoice = Invoice::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $appointment->patient_id,
            'visit_id' => null,
            'appointment_id' => $appointment->id,
            'issued_by' => $userId,
            'invoice_number' => $invoiceNumber,
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'due_at' => null,
            'subtotal_amount' => $cost,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $cost,
            'paid_amount' => 0,
            'balance_amount' => $cost,
            'notes' => null,
        ]);

        $invoice->items()->create([
            'clinic_id' => $clinicId,
            'service_code' => $appointment->appointment_type ?? 'first_visit',
            'description' => $appointment->appointment_type === 'review' ? 'مراجعة' : 'كشفية أولى',
            'quantity' => 1,
            'unit_price' => $cost,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => $cost,
        ]);

        return $invoice;
    }

    private function recordPaymentForInvoice(int $clinicId, int $userId, Invoice $invoice): void
    {
        $cost = (float) $invoice->total_amount;

        if ($cost <= 0) {
            return;
        }

        try {
            $this->recordPaymentAction->handle(
                clinicId: $clinicId,
                invoiceId: $invoice->id,
                userId: $userId,
                payload: [
                    'amount' => $cost,
                    'method' => 'cash',
                    'paid_at' => now()->toDateTimeString(),
                ],
            );
        } catch (\Throwable) {
            // Payment recording failure should not prevent appointment creation
        }
    }

    private function generateInvoiceNumber(int $clinicId): string
    {
        $today = now()->toDateString();
        $sequence = (int) Invoice::query()
            ->forClinic($clinicId)
            ->whereDate('created_at', $today)
            ->count() + 1;

        return sprintf('INV-%s-%04d', now()->format('Ymd'), $sequence);
    }

    private function createDoctorEntitlementIfNeeded(int $clinicId, Appointment $appointment): void
    {
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

        if ($doctorProfile === null) {
            return;
        }

        if ($doctorProfile->compensation_type !== DoctorProfile::COMPENSATION_PERCENTAGE) {
            return;
        }

        $percentage = (float) ($doctorProfile->compensationAmount() ?? 0);

        if ($percentage <= 0) {
            return;
        }

        $entitlementAmount = $cost * ($percentage / 100);

        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinicId,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => $cost,
            'percentage' => $percentage,
            'entitlement_amount' => $entitlementAmount,
            'compensation_type' => $doctorProfile->compensation_type,
            'compensation_value' => $percentage,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => Carbon::parse($appointment->scheduled_for)->toDateString(),
        ]);
    }
}
