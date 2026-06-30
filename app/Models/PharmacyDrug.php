<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PharmacyDrugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PharmacyDrug extends BaseModel
{
    /** @use HasFactory<PharmacyDrugFactory> */
    use HasFactory, SoftDeletes;

    public const FORM_TABLET = 'tablet';

    public const FORM_CAPSULE = 'capsule';

    public const FORM_SYRUP = 'syrup';

    public const FORM_INJECTION = 'injection';

    public const FORM_CREAM = 'cream';

    public const FORM_DROPS = 'drops';

    public const FORM_INHALER = 'inhaler';

    public const FORM_OTHER = 'other';

    public const UNIT_BOX = 'box';

    public const UNIT_STRIP = 'strip';

    public const UNIT_TABLET = 'tablet';

    public const UNIT_BOTTLE = 'bottle';

    public const UNIT_AMPOULE = 'ampoule';

    public const UNIT_VIAL = 'vial';

    public const UNIT_TUBE = 'tube';

    protected $attributes = [
        'unit_price' => 0,
        'min_stock_level' => 0,
        'current_stock' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'date',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DrugBatch::class, 'pharmacy_drug_id');
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class, 'pharmacy_drug_id');
    }

    public function dispenseItems(): HasMany
    {
        return $this->hasMany(PharmacyDispenseItem::class, 'pharmacy_drug_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(PharmacyStockMovement::class, 'pharmacy_drug_id');
    }

    public function isLowStock(): bool
    {
        return (int) $this->current_stock <= (int) $this->min_stock_level;
    }

    public function nearestExpiryBatch()
    {
        return $this->batches()
            ->where('quantity', '>', 0)
            ->notExpired()
            ->orderBy('expiry_date')
            ->first();
    }

    public function totalBatchQuantity(): int
    {
        return (int) $this->batches()
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }

    public function nearestExpiryDate(): ?string
    {
        $batch = $this->batches()
            ->where('quantity', '>', 0)
            ->notExpired()
            ->orderBy('expiry_date')
            ->first();

        return $batch?->expiry_date?->toDateString();
    }
}
