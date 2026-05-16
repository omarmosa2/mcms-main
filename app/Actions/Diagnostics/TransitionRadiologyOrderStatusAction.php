<?php

namespace App\Actions\Diagnostics;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\RadiologyOrder;
use Illuminate\Validation\ValidationException;

class TransitionRadiologyOrderStatusAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(int $clinicId, int $radiologyOrderId, int $userId, string $newStatus, array $context = []): RadiologyOrder
    {
        $order = RadiologyOrder::query()
            ->forClinic($clinicId)
            ->findOrFail($radiologyOrderId);

        $currentStatus = $order->status;

        if (! in_array($newStatus, RadiologyOrder::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid radiology order status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $order->only(['status', 'completed_at', 'reported_at', 'canceled_at', 'cancel_reason']);

        $order->status = $newStatus;

        if ($newStatus === RadiologyOrder::STATUS_COMPLETED) {
            $order->completed_at = now();
        }

        if ($newStatus === RadiologyOrder::STATUS_REPORTED) {
            $order->reported_at = now();
        }

        if ($newStatus === RadiologyOrder::STATUS_CANCELED) {
            $order->canceled_at = now();
            $order->cancel_reason = (string) ($context['cancel_reason'] ?? '');
        }

        if ($newStatus !== RadiologyOrder::STATUS_CANCELED) {
            $order->cancel_reason = null;
        }

        $order->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'diagnostics.radiology_orders.transition_status',
            auditable: $order,
            oldValues: $oldValues,
            newValues: $order->only(['status', 'completed_at', 'reported_at', 'canceled_at', 'cancel_reason']),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
            ],
        );

        return $order->fresh();
    }
}
