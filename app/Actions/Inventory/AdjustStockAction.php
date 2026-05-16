<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\PharmacyDrug;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

class AdjustStockAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $drugId,
        int $quantityChange,
        string $reason,
        ?string $notes = null,
    ): StockAdjustment {
        return DB::transaction(function () use ($clinicId, $userId, $drugId, $quantityChange, $reason, $notes): StockAdjustment {
            $drug = PharmacyDrug::query()
                ->forClinic($clinicId)
                ->findOrFail($drugId);

            $adjustment = StockAdjustment::query()->create([
                'clinic_id' => $clinicId,
                'pharmacy_drug_id' => $drugId,
                'quantity_change' => $quantityChange,
                'reason' => $reason,
                'adjusted_by' => $userId,
                'adjusted_at' => now(),
                'notes' => $notes,
            ]);

            $newStock = max(0, $drug->current_stock + $quantityChange);
            $drug->current_stock = $newStock;
            $drug->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'inventory.adjust_stock',
                metadata: [
                    'drug_id' => $drugId,
                    'quantity_change' => $quantityChange,
                    'previous_stock' => $drug->current_stock - $quantityChange,
                    'new_stock' => $newStock,
                    'reason' => $reason,
                ],
            );

            return $adjustment;
        });
    }
}
