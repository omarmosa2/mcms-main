<?php

namespace App\Actions\Cashbox;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Cashbox;
use Illuminate\Validation\ValidationException;

class OpenCashboxAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        array $payload,
    ): Cashbox {
        $today = now()->toDateString();

        $existingOpen = Cashbox::query()
            ->forClinic($clinicId)
            ->where('box_date', $today)
            ->where('status', Cashbox::STATUS_OPEN)
            ->exists();

        if ($existingOpen) {
            throw ValidationException::withMessages([
                'box_date' => 'A cashbox is already open for today.',
            ]);
        }

        $cashbox = Cashbox::query()->create([
            'clinic_id' => $clinicId,
            'opening_balance' => (float) ($payload['opening_balance'] ?? 0),
            'total_income' => 0,
            'total_expenses' => 0,
            'closing_balance' => (float) ($payload['opening_balance'] ?? 0),
            'box_date' => $today,
            'status' => Cashbox::STATUS_OPEN,
            'opened_by' => $userId,
            'opened_at' => now(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'cashbox.open',
            auditable: $cashbox,
            metadata: [
                'cashbox_id' => $cashbox->id,
                'opening_balance' => $cashbox->opening_balance,
            ],
        );

        return $cashbox;
    }
}
