<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Services\Cache\CacheService;
use Illuminate\Validation\ValidationException;

class TransitionAppointmentStatusAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
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

        $currentStatus = $appointment->status;
        $nextStatus = (string) $payload['status'];

        if (! $this->canTransition($currentStatus, $nextStatus)) {
            throw ValidationException::withMessages([
                'status' => "انتقال غير صالح من [{$currentStatus}] إلى [{$nextStatus}].",
            ]);
        }

        $oldValues = $appointment->only([
            'status',
            'arrived_at',
            'completed_at',
            'canceled_at',
            'cancel_reason',
        ]);

        $appointment->status = $nextStatus;

        if ($nextStatus === Appointment::STATUS_ARRIVED) {
            $appointment->arrived_at = now();
        }

        if ($nextStatus === Appointment::STATUS_COMPLETED) {
            $appointment->completed_at = now();
        }

        if ($nextStatus === Appointment::STATUS_CANCELED) {
            $appointment->canceled_at = now();
            $appointment->cancel_reason = (string) ($payload['cancel_reason'] ?? '');
        }

        if ($nextStatus !== Appointment::STATUS_CANCELED) {
            $appointment->cancel_reason = null;
        }

        $appointment->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'appointments.transition_status',
            auditable: $appointment,
            oldValues: $oldValues,
            newValues: $appointment->only([
                'status',
                'arrived_at',
                'completed_at',
                'canceled_at',
                'cancel_reason',
            ]),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
            ],
        );

        $this->cacheService->invalidateDashboardStats($clinicId);
        $this->cacheService->invalidateDropdowns($clinicId);

        return $appointment->fresh();
    }

    private function canTransition(string $currentStatus, string $nextStatus): bool
    {
        $allowedTransitions = [
            Appointment::STATUS_SCHEDULED => [
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_ARRIVED,
                Appointment::STATUS_CANCELED,
                Appointment::STATUS_NO_SHOW,
            ],
            Appointment::STATUS_CONFIRMED => [
                Appointment::STATUS_ARRIVED,
                Appointment::STATUS_CANCELED,
                Appointment::STATUS_NO_SHOW,
            ],
            Appointment::STATUS_ARRIVED => [
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELED,
                Appointment::STATUS_NO_SHOW,
            ],
            Appointment::STATUS_COMPLETED => [],
            Appointment::STATUS_CANCELED => [],
            Appointment::STATUS_NO_SHOW => [],
        ];

        return in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true);
    }
}
