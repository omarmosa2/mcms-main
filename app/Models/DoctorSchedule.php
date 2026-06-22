<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSchedule extends BaseModel
{
    public const DAYS = [0, 1, 2, 3, 4, 5, 6];

    protected $fillable = [
        'doctor_profile_id',
        'clinic_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'day_of_week' => 'integer',
        ];
    }

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function getDayNameAttribute(): string
    {
        return WeekDay::arabicName((string) $this->day_of_week);
    }
}
