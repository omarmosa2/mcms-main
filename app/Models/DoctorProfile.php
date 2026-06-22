<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorProfile extends BaseModel
{
    /** @use HasFactory<DoctorProfileFactory> */
    use HasFactory;

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    public const COMPENSATION_PERCENTAGE = 'percentage';

    public const COMPENSATION_WEEKLY_FIXED = 'weekly_fixed';

    public const COMPENSATION_MONTHLY_FIXED = 'monthly_fixed';

    protected $fillable = [
        'clinic_id',
        'user_id',
        'full_name',
        'gender',
        'specialty',
        'phone',
        'email',
        'username',
        'employment_start_date',
        'compensation_type',
        'compensation_value',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'compensation_value' => 'decimal:2',
            'employment_start_date' => 'date',
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

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_profile_id');
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
