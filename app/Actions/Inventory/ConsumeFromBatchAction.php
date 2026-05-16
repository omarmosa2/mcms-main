<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DrugBatch;
use App\Models\PharmacyDrug;
use Illuminate\Support\Facades\DB;

class ConsumeFromBatchAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $drugId,
        int $quantity,
    ): array {
        return DB::transaction(function () use ($clinicId, $userId, $drugId, $quantity): array {
            $drug = PharmacyDrug::query()
                ->forClinic($clinicId)
                ->findOrFail($drugId);

            if ($drug->current_stock < $quantity) {
                abort(422, 'Insufficient stock for this drug.');
            }

            $batches = DrugBatch::query()
                ->forClinic($clinicId)
                ->where('pharmacy_drug_id', $drugId)
                ->where('quantity', '>', 0)
                ->notExpired()
                ->orderBy('expiry_date')
                ->get();

            $remaining = $quantity;
            $consumedBatches = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $consumeAmount = min($remaining, $batch->quantity);
                $batch->consume($consumeAmount);
                $remaining -= $consumeAmount;

                $consumedBatches[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'consumed' => $consumeAmount,
                ];
            }

            if ($remaining > 0) {
                abort(422, 'Insufficient available stock in batches.');
            }

            $drug->current_stock -= $quantity;
            $drug->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'inventory.consume_from_batches',
                metadata: [
                    'drug_id' => $drugId,
                    'quantity' => $quantity,
                    'batches' => $consumedBatches,
                ],
            );

            return $consumedBatches;
        });
    }
}
