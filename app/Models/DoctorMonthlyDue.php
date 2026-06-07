<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorMonthlyDueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorMonthlyDue extends BaseModel
{
    /** @use HasFactory<DoctorMonthlyDueFactory> */
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'fixed_weekly_amount' => 'decimal:2',
            'fixed_monthly_amount' => 'decimal:2',
            'visits_total_amount' => 'decimal:2',
            'deductions_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DoctorDuePayment::class);
    }
}
