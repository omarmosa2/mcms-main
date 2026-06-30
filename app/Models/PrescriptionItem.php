<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrescriptionItem extends BaseModel
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_SUBSTITUTED = 'substituted';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'substitution_allowed' => 'boolean',
            'quantity' => 'integer',
            'quantity_dispensed' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(PharmacyDrug::class, 'pharmacy_drug_id');
    }

    public function dispensedBatch(): BelongsTo
    {
        return $this->belongsTo(DrugBatch::class, 'dispensed_batch_id');
    }

    public function isFullyDispensed(): bool
    {
        return (int) $this->quantity_dispensed >= (int) $this->quantity;
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->quantity_dispensed);
    }
}
