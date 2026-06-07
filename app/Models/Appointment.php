<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends BaseModel
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_NO_SHOW = 'no_show';

    public const TYPE_FIRST_VISIT = 'first_visit';

    public const TYPE_REVIEW = 'review';

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_CANCELED,
        self::STATUS_NO_SHOW,
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function doctorEntitlement(): HasMany
    {
        return $this->hasMany(DoctorAppointmentEntitlement::class);
    }
}
