<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DoctorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
