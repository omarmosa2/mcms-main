<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadiologyOrder extends BaseModel
{
    use SoftDeletes;

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_CANCELED = 'canceled';

    public const TERMINAL_STATUSES = [
        self::STATUS_REPORTED,
        self::STATUS_CANCELED,
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_ORDERED => [self::STATUS_COMPLETED, self::STATUS_CANCELED],
        self::STATUS_COMPLETED => [self::STATUS_REPORTED, self::STATUS_CANCELED],
        self::STATUS_REPORTED => [],
        self::STATUS_CANCELED => [],
    ];

    protected $attributes = [
        'status' => self::STATUS_ORDERED,
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
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

    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(RadiologyReport::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RadiologyImage::class);
    }
}
