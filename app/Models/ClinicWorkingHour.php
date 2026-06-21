<?php

namespace App\Models;

use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicWorkingHour extends Model
{
    public const DAYS = [0, 1, 2, 3, 4, 5, 6];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'day_of_week' => 'integer',
        ];
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
