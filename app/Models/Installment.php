<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installment extends BaseModel
{
    /** @use HasFactory<InstallmentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'payment_plan_id' => 'integer',
            'invoice_id' => 'integer',
            'installment_number' => 'integer',
            'amount' => 'integer',
            'due_date' => 'date',
            'paid_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Installment $installment): void {
            $installment->validateFinancialAmounts();
        });

        static::updating(function (Installment $installment): void {
            if ($installment->isDirty(['installment_number', 'amount', 'paid_amount'])) {
                $installment->validateFinancialAmounts();
            }
        });
    }

    public function validateFinancialAmounts(): void
    {
        if ($this->installment_number <= 0) {
            throw new \InvalidArgumentException('Installment number must be positive.');
        }

        if ($this->amount <= 0) {
            throw new \InvalidArgumentException('Installment amount must be positive.');
        }

        if ($this->paid_amount < 0) {
            throw new \InvalidArgumentException('Installment paid_amount cannot be negative.');
        }

        if ($this->paid_amount > $this->amount) {
            throw new \InvalidArgumentException('Installment paid_amount cannot exceed amount.');
        }
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'pending')->where('due_date', '<=', now());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')->where('due_date', '<', now());
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date < now();
    }

    public function markAsPaid(int $amount): void
    {
        $this->paid_amount = min($amount, $this->amount);
        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();
    }

    public function remainingAmount(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }
}
