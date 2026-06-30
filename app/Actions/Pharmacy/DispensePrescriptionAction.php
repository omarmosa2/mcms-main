<?php

namespace App\Actions\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DrugBatch;
use App\Models\PharmacyDispense;
use App\Models\PharmacyDrug;
use App\Models\PharmacyStockMovement;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensePrescriptionAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<int, array{prescription_item_id: int, quantity: int, batch_id?: int|null}>  $itemsData
     */
    public function handle(
        int $clinicId,
        int $userId,
        Prescription $prescription,
        array $itemsData,
        ?string $notes = null,
    ): PharmacyDispense {
        return DB::transaction(function () use ($clinicId, $userId, $prescription, $itemsData, $notes): PharmacyDispense {
            $prescription->load(['items']);

            if ($prescription->status === Prescription::STATUS_DISPENSED) {
                throw ValidationException::withMessages([
                    'prescription' => 'الوصفة تم صرفها بالفعل.',
                ]);
            }

            if ($prescription->status === Prescription::STATUS_CANCELED) {
                throw ValidationException::withMessages([
                    'prescription' => 'لا يمكن صرف وصفة ملغية.',
                ]);
            }

            $dispense = PharmacyDispense::query()->create([
                'clinic_id' => $clinicId,
                'prescription_id' => $prescription->id,
                'dispensed_by' => $userId,
                'dispensed_at' => now(),
                'total_amount' => 0,
                'notes' => $notes,
            ]);

            $totalAmount = 0.0;
            $dispensedCount = 0;
            $totalItems = $prescription->items->count();

            foreach ($itemsData as $itemData) {
                $prescriptionItem = $prescription->items->firstWhere('id', (int) $itemData['prescription_item_id']);

                if (! $prescriptionItem) {
                    throw ValidationException::withMessages([
                        'items' => 'عنصر الوصفة غير موجود.',
                    ]);
                }

                $quantity = (int) $itemData['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                $drugId = $prescriptionItem->pharmacy_drug_id;

                if ($drugId === null) {
                    throw ValidationException::withMessages([
                        'items' => "العنصر '{$prescriptionItem->medication_name}' غير مرتبط بدواء في الصيدلية.",
                    ]);
                }

                $drug = PharmacyDrug::query()
                    ->forClinic($clinicId)
                    ->whereKey($drugId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $batchId = $itemData['batch_id'] ?? null;

                if ($batchId) {
                    $batch = DrugBatch::query()
                        ->forClinic($clinicId)
                        ->whereKey($batchId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($batch->isExpired()) {
                        throw ValidationException::withMessages([
                            'batch' => "الدفعة '{$batch->batch_number}' منتهية الصلاحية.",
                        ]);
                    }

                    if ($batch->quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'batch' => "الكمية غير متوفرة في الدفعة '{$batch->batch_number}'. المتاح: {$batch->quantity}",
                        ]);
                    }

                    $previousQuantity = $batch->quantity;
                    $batch->consume($quantity);

                    PharmacyStockMovement::query()->create([
                        'clinic_id' => $clinicId,
                        'pharmacy_drug_id' => $drug->id,
                        'batch_id' => $batch->id,
                        'movement_type' => PharmacyStockMovement::TYPE_PRESCRIPTION_DISPENSE,
                        'quantity' => $quantity,
                        'previous_quantity' => $previousQuantity,
                        'new_quantity' => $batch->quantity,
                        'reference_type' => 'prescription',
                        'reference_id' => $prescription->id,
                        'notes' => "صرف وصفة #{$prescription->prescription_number}",
                        'created_by' => $userId,
                    ]);
                } else {
                    $this->consumeFefo($clinicId, $drug, $quantity, $prescription, $userId);
                }

                $previousDrugStock = $drug->current_stock;
                $drug->current_stock = (int) $drug->current_stock - $quantity;
                $drug->save();

                PharmacyStockMovement::query()->create([
                    'clinic_id' => $clinicId,
                    'pharmacy_drug_id' => $drug->id,
                    'batch_id' => $batchId,
                    'movement_type' => PharmacyStockMovement::TYPE_PRESCRIPTION_DISPENSE,
                    'quantity' => $quantity,
                    'previous_quantity' => (int) $previousDrugStock,
                    'new_quantity' => (int) $drug->current_stock,
                    'reference_type' => 'prescription',
                    'reference_id' => $prescription->id,
                    'notes' => "صرف وصفة #{$prescription->prescription_number}",
                    'created_by' => $userId,
                ]);

                $lineTotal = round((float) $drug->unit_price * $quantity, 2);
                $totalAmount += $lineTotal;

                $dispense->items()->create([
                    'clinic_id' => $clinicId,
                    'prescription_item_id' => $prescriptionItem->id,
                    'pharmacy_drug_id' => $drug->id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $drug->unit_price,
                    'line_total' => $lineTotal,
                ]);

                $prescriptionItem->quantity_dispensed = (int) $prescriptionItem->quantity_dispensed + $quantity;

                if ($prescriptionItem->isFullyDispensed()) {
                    $prescriptionItem->status = PrescriptionItem::STATUS_DISPENSED;
                } else {
                    $prescriptionItem->status = PrescriptionItem::STATUS_DISPENSED;
                }

                $prescriptionItem->save();
                $dispensedCount++;
            }

            $dispense->total_amount = round($totalAmount, 2);
            $dispense->save();

            $allDispensed = $prescription->items->fresh()->every(
                fn (PrescriptionItem $item): bool => $item->status === PrescriptionItem::STATUS_DISPENSED
            );

            $anyDispensed = $dispensedCount > 0;

            if ($allDispensed) {
                $prescription->status = Prescription::STATUS_DISPENSED;
                $prescription->dispensed_at = now();
            } elseif ($anyDispensed) {
                $prescription->status = Prescription::STATUS_PARTIALLY_DISPENSED;
            }

            $prescription->dispensed_by = $userId;
            $prescription->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'pharmacy.prescriptions.dispense',
                auditable: $prescription,
                metadata: [
                    'dispense_id' => $dispense->id,
                    'items_count' => $dispensedCount,
                    'total_amount' => (float) $dispense->total_amount,
                    'status' => $prescription->status,
                ],
            );

            return $dispense->load(['items.drug', 'prescription']);
        });
    }

    private function consumeFefo(
        int $clinicId,
        PharmacyDrug $drug,
        int $quantity,
        Prescription $prescription,
        int $userId,
    ): void {
        $batches = DrugBatch::query()
            ->forClinic($clinicId)
            ->where('pharmacy_drug_id', $drug->id)
            ->where('quantity', '>', 0)
            ->notExpired()
            ->orderBy('expiry_date')
            ->lockForUpdate()
            ->get();

        $available = $batches->sum('quantity');

        if ($available < $quantity) {
            if ((int) $drug->current_stock >= $quantity) {
                return;
            }

            throw ValidationException::withMessages([
                'stock' => "الكمية غير متوفرة للدواء '{$drug->trade_name}'. المتاح: {$available}",
            ]);
        }

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $consumeAmount = min($remaining, $batch->quantity);
            $previousQuantity = $batch->quantity;
            $batch->consume($consumeAmount);
            $remaining -= $consumeAmount;

            PharmacyStockMovement::query()->create([
                'clinic_id' => $clinicId,
                'pharmacy_drug_id' => $drug->id,
                'batch_id' => $batch->id,
                'movement_type' => PharmacyStockMovement::TYPE_PRESCRIPTION_DISPENSE,
                'quantity' => $consumeAmount,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $batch->quantity,
                'reference_type' => 'prescription',
                'reference_id' => $prescription->id,
                'notes' => "صرف وصفة #{$prescription->prescription_number} (FEFO)",
                'created_by' => $userId,
            ]);
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'stock' => "الكمية غير متوفرة في الدفعات للدواء '{$drug->trade_name}'.",
            ]);
        }
    }
}
