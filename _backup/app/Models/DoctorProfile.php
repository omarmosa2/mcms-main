<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorProfile extends BaseModel
{
    /** @use HasFactory<DoctorProfileFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_INACTIVE = 'inactive';

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    public const COMPENSATION_PERCENTAGE = 'percentage';

    public const COMPENSATION_WEEKLY = 'weekly';

    public const COMPENSATION_MONTHLY = 'monthly';

    protected function casts(): array
    {
        return [
            'consultation_duration_minutes' => 'integer',
            'compensation_value' => 'decimal:2',
            'work_start_date' => 'date',
            'work_schedule' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The schedule table is keyed by this profile's primary key.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_id');
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(DoctorSalaryPayment::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(DoctorDeduction::class);
    }

    public function appointmentEntitlements(): HasMany
    {
        return $this->hasMany(DoctorAppointmentEntitlement::class);
    }

    public function monthlyDues(): HasMany
    {
        return $this->hasMany(DoctorMonthlyDue::class, 'doctor_id');
    }
}
