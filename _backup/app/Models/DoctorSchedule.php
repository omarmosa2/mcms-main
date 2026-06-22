<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorSchedule extends BaseModel
{
    use SoftDeletes;

    public const DAYS = WeekDay::DAYS;

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'day_of_week' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_id');
    }

    public function getDayNameAttribute(): string
    {
        return WeekDay::arabicName((string) $this->day_of_week);
    }
}
