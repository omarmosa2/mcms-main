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

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class, 'pharmacy_drug_id');
    }

    public function dispenseItems(): HasMany
    {
        return $this->hasMany(PharmacyDispenseItem::class, 'pharmacy_drug_id');
    }
}
