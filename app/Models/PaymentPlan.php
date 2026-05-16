<?php

namespace App\Models;

use App\Concerns\Cachable;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PaymentPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentPlan extends BaseModel
{
    /** @use HasFactory<PaymentPlanFactory> */
    use Cachable, HasFactory, SoftDeletes;

    protected string $cachePrefix = 'payment_plans';

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'installment_count' => 'integer',
            'min_amount' => 'integer',
            'is_active' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentPlan $plan): void {
            $plan->validateFinancialAmounts();
        });

        static::updating(function (PaymentPlan $plan): void {
            if ($plan->isDirty(['installment_count', 'min_amount'])) {
                $plan->validateFinancialAmounts();
            }
        });
    }

    public function validateFinancialAmounts(): void
    {
        if ($this->installment_count <= 0) {
            throw new \InvalidArgumentException('Payment plan installment_count must be positive.');
        }

        if ($this->min_amount < 0) {
            throw new \InvalidArgumentException('Payment plan min_amount cannot be negative.');
        }
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
