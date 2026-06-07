<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorAppointmentEntitlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorAppointmentEntitlement extends BaseModel
{
    /** @use HasFactory<DoctorAppointmentEntitlementFactory> */
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return [
            'appointment_cost' => 'decimal:2',
            'percentage' => 'decimal:2',
            'entitlement_amount' => 'decimal:2',
            'appointment_date' => 'date',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
