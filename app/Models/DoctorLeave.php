<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorLeave extends BaseModel
{
    /** @use HasFactory<DoctorLeaveFactory> */
    use HasFactory;

    public const TYPE_FULL_DAY = 'full_day';

    public const TYPE_HOURLY = 'hourly';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELED = 'canceled';

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id', 'doctor_id');
    }
}
