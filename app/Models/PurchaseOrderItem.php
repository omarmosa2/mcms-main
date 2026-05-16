<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends BaseModel
{
    use SoftDeletes;

    protected $attributes = [
        'quantity_received' => 0,
        'unit_cost' => 0,
        'line_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function pharmacyDrug(): BelongsTo
    {
        return $this->belongsTo(PharmacyDrug::class, 'pharmacy_drug_id');
    }
}
