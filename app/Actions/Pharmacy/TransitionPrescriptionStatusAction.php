<?php

namespace App\Actions\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Prescription;
use Illuminate\Validation\ValidationException;

class TransitionPrescriptionStatusAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(int $clinicId, int $prescriptionId, int $userId, string $newStatus, array $context = []): Prescription
    {
        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->findOrFail($prescriptionId);

        $currentStatus = $prescription->status;

        if (! in_array($newStatus, Prescription::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid prescription status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $prescription->only(['status', 'issued_at', 'dispensed_at', 'canceled_at', 'cancel_reason']);

        $prescription->status = $newStatus;

        if ($newStatus === Prescription::STATUS_ISSUED) {
            $prescription->issued_at = now();
        }

        if ($newStatus === Prescription::STATUS_DISPENSED) {
            $prescription->dispensed_at = now();
        }

        if ($newStatus === Prescription::STATUS_CANCELED) {
            $prescription->canceled_at = now();
            $prescription->cancel_reason = (string) ($context['cancel_reason'] ?? '');
        }

        if ($newStatus !== Prescription::STATUS_CANCELED) {
            $prescription->cancel_reason = null;
        }

        $prescription->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'pharmacy.prescriptions.transition_status',
            auditable: $prescription,
            oldValues: $oldValues,
            newValues: $prescription->only(['status', 'issued_at', 'dispensed_at', 'canceled_at', 'cancel_reason']),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
            ],
        );

        return $prescription->fresh();
    }
}
