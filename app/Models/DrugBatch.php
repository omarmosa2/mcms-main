<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Database\Factories\DrugBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrugBatch extends BaseModel
{
    /** @use HasFactory<DrugBatchFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'pharmacy_drug_id' => 'integer',
            'quantity' => 'integer',
            'initial_quantity' => 'integer',
            'expiry_date' => 'date',
            'received_at' => 'datetime',
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

    public function scopeNotExpired($query)
    {
        return $query->where('expiry_date', '>', now());
    }

    public function scopeWithAvailableStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date < now();
    }

    public function consume(int $quantity): void
    {
        $this->quantity = max(0, $this->quantity - $quantity);
        $this->save();
    }

    public function remainingDays(): int
    {
        return max(0, CarbonImmutable::now()->diffInDays($this->expiry_date, false));
    }
}
