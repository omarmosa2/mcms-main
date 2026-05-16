<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PharmacyDispenseItem extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function dispense(): BelongsTo
    {
        return $this->belongsTo(PharmacyDispense::class, 'pharmacy_dispense_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(PharmacyDrug::class, 'pharmacy_drug_id');
    }
}
