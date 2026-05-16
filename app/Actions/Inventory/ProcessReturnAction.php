<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DrugBatch;
use App\Models\InventoryReturn;
use App\Models\PharmacyDrug;
use Illuminate\Support\Facades\DB;

class ProcessReturnAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $drugId,
        int $quantity,
        string $reason,
        bool $returnedToSupplier = false,
        ?int $supplierId = null,
        ?int $batchId = null,
        ?string $notes = null,
    ): InventoryReturn {
        return DB::transaction(function () use ($clinicId, $userId, $drugId, $quantity, $reason, $returnedToSupplier, $supplierId, $batchId, $notes): InventoryReturn {
            $drug = PharmacyDrug::query()
                ->forClinic($clinicId)
                ->findOrFail($drugId);

            if ($batchId !== null) {
                $batch = DrugBatch::query()
                    ->forClinic($clinicId)
                    ->findOrFail($batchId);

                if ($batch->quantity < $quantity) {
                    abort(422, 'Insufficient quantity in batch.');
                }

                $batch->quantity -= $quantity;
                $batch->save();
            }

            $return = InventoryReturn::query()->create([
                'clinic_id' => $clinicId,
                'pharmacy_drug_id' => $drugId,
                'drug_batch_id' => $batchId,
                'quantity' => $quantity,
                'reason' => $reason,
                'returned_to_supplier' => $returnedToSupplier,
                'supplier_id' => $returnedToSupplier ? $supplierId : null,
                'returned_at' => now(),
                'notes' => $notes,
            ]);

            $drug->current_stock = max(0, $drug->current_stock - $quantity);
            $drug->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'inventory.return',
                metadata: [
                    'drug_id' => $drugId,
                    'quantity' => $quantity,
                    'reason' => $reason,
                    'returned_to_supplier' => $returnedToSupplier,
                ],
            );

            return $return;
        });
    }
}
