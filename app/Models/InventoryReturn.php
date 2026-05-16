<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\InventoryReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryReturn extends BaseModel
{
    /** @use HasFactory<InventoryReturnFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'pharmacy_drug_id' => 'integer',
            'drug_batch_id' => 'integer',
            'quantity' => 'integer',
            'returned_to_supplier' => 'boolean',
            'supplier_id' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(PharmacyDrug::class, 'pharmacy_drug_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DrugBatch::class, 'drug_batch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
