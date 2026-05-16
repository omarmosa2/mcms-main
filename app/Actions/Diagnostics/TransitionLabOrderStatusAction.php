<?php

namespace App\Actions\Diagnostics;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\LabOrder;
use Illuminate\Validation\ValidationException;

class TransitionLabOrderStatusAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(int $clinicId, int $labOrderId, int $userId, string $newStatus, array $context = []): LabOrder
    {
        $order = LabOrder::query()
            ->forClinic($clinicId)
            ->findOrFail($labOrderId);

        $currentStatus = $order->status;

        if (! in_array($newStatus, LabOrder::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid lab order status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $order->only(['status', 'sample_collected_at', 'resulted_at', 'canceled_at', 'cancel_reason']);

        $order->status = $newStatus;

        if ($newStatus === LabOrder::STATUS_SAMPLE_COLLECTED) {
            $order->sample_collected_at = now();
        }

        if ($newStatus === LabOrder::STATUS_RESULTED) {
            $order->resulted_at = now();
        }

        if ($newStatus === LabOrder::STATUS_CANCELED) {
            $order->canceled_at = now();
            $order->cancel_reason = (string) ($context['cancel_reason'] ?? '');
        }

        if ($newStatus !== LabOrder::STATUS_CANCELED) {
            $order->cancel_reason = null;
        }

        $order->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'diagnostics.lab_orders.transition_status',
            auditable: $order,
            oldValues: $oldValues,
            newValues: $order->only(['status', 'sample_collected_at', 'resulted_at', 'canceled_at', 'cancel_reason']),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
            ],
        );

        return $order->fresh();
    }
}
