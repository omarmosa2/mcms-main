<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorPayment extends BaseModel
{
    /** @use HasFactory<DoctorPaymentFactory> */
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_WEEKLY = 'weekly';

    public const TYPE_MONTHLY = 'monthly';

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
