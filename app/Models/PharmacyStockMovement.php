<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyStockMovement extends BaseModel
{
    public const TYPE_STOCK_IN = 'stock_in';

    public const TYPE_STOCK_OUT = 'stock_out';

    public const TYPE_PRESCRIPTION_DISPENSE = 'prescription_dispense';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_RETURN = 'return';

    public const TYPE_EXPIRED = 'expired';

    public const TYPE_DAMAGED = 'damaged';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_quantity' => 'integer',
            'new_quantity' => 'integer',
            'reference_id' => 'integer',
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
        return $this->belongsTo(DrugBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForDrug($query, int $drugId)
    {
        return $query->where('pharmacy_drug_id', $drugId);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }
}
