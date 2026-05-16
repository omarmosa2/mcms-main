<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\InventoryAlert;
use App\Models\PharmacyDrug;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pharmacy:scan-alerts')]
#[Description('Scan pharmacy inventory for low-stock and expiry alerts')]
class ScanPharmacyInventoryAlertsCommand extends Command
{
    public function handle(): int
    {
        $createdAlerts = 0;

        Clinic::query()
            ->where('is_active', true)
            ->select(['id'])
            ->chunkById(50, function ($clinics) use (&$createdAlerts): void {
                foreach ($clinics as $clinic) {
                    $createdAlerts += $this->scanClinicInventory((int) $clinic->id);
                }
            });

        $this->info(sprintf('Inventory scan completed. Created %d alert(s).', $createdAlerts));

        return self::SUCCESS;
    }

    private function scanClinicInventory(int $clinicId): int
    {
        $created = 0;
        $nearExpiryDate = now()->addDays(30)->toDateString();

        PharmacyDrug::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->where(function ($query) use ($nearExpiryDate): void {
                $query
                    ->whereColumn('current_stock', '<=', 'min_stock_level')
                    ->orWhereDate('expires_at', '<=', $nearExpiryDate);
            })
            ->orderBy('id')
            ->chunkById(100, function ($drugs) use ($clinicId, &$created): void {
                /** @var PharmacyDrug $drug */
                foreach ($drugs as $drug) {
                    if ((int) $drug->current_stock <= (int) $drug->min_stock_level) {
                        $alert = InventoryAlert::query()->firstOrCreate(
                            [
                                'clinic_id' => $clinicId,
                                'pharmacy_drug_id' => $drug->id,
                                'type' => InventoryAlert::TYPE_LOW_STOCK,
                                'status' => InventoryAlert::STATUS_OPEN,
                            ],
                            [
                                'severity' => InventoryAlert::SEVERITY_HIGH,
                                'message' => sprintf(
                                    'Low stock for %s: %d remaining (minimum %d).',
                                    $drug->trade_name,
                                    (int) $drug->current_stock,
                                    (int) $drug->min_stock_level,
                                ),
                                'metadata' => [
                                    'current_stock' => (int) $drug->current_stock,
                                    'min_stock_level' => (int) $drug->min_stock_level,
                                ],
                                'detected_at' => now(),
                            ],
                        );

                        if ($alert->wasRecentlyCreated) {
                            $created++;
                        }
                    }

                    if ($drug->expires_at === null) {
                        continue;
                    }

                    $isExpired = $drug->expires_at->isPast();
                    $isNearExpiry = $drug->expires_at->isFuture() && $drug->expires_at->lessThanOrEqualTo(now()->addDays(30));

                    if (! $isExpired && ! $isNearExpiry) {
                        continue;
                    }

                    $type = $isExpired
                        ? InventoryAlert::TYPE_EXPIRED
                        : InventoryAlert::TYPE_NEAR_EXPIRY;

                    $severity = $isExpired
                        ? InventoryAlert::SEVERITY_HIGH
                        : InventoryAlert::SEVERITY_MEDIUM;

                    $message = $isExpired
                        ? sprintf('Expired item detected: %s (expired on %s).', $drug->trade_name, $drug->expires_at->toDateString())
                        : sprintf('Near-expiry item: %s (expires on %s).', $drug->trade_name, $drug->expires_at->toDateString());

                    $alert = InventoryAlert::query()->firstOrCreate(
                        [
                            'clinic_id' => $clinicId,
                            'pharmacy_drug_id' => $drug->id,
                            'type' => $type,
                            'status' => InventoryAlert::STATUS_OPEN,
                        ],
                        [
                            'severity' => $severity,
                            'message' => $message,
                            'metadata' => [
                                'expires_at' => $drug->expires_at->toDateString(),
                            ],
                            'detected_at' => now(),
                        ],
                    );

                    if ($alert->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
