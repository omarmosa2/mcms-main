<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DrugBatch;
use App\Models\PharmacyDrug;
use Illuminate\Support\Facades\DB;

class CreateDrugBatchAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $drugId,
        string $batchNumber,
        int $quantity,
        string $expiryDate,
    ): DrugBatch {
        return DB::transaction(function () use ($clinicId, $userId, $drugId, $batchNumber, $quantity, $expiryDate): DrugBatch {
            $drug = PharmacyDrug::query()
                ->forClinic($clinicId)
                ->findOrFail($drugId);

            $batch = DrugBatch::query()->create([
                'clinic_id' => $clinicId,
                'pharmacy_drug_id' => $drugId,
                'batch_number' => $batchNumber,
                'quantity' => $quantity,
                'initial_quantity' => $quantity,
                'expiry_date' => $expiryDate,
                'received_at' => now(),
            ]);

            $drug->current_stock += $quantity;
            $drug->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'inventory.create_batch',
                metadata: [
                    'drug_id' => $drugId,
                    'batch_number' => $batchNumber,
                    'quantity' => $quantity,
                    'expiry_date' => $expiryDate,
                ],
            );

            return $batch;
        });
    }
}
