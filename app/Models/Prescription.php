<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends BaseModel
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_CANCELED = 'canceled';

    public const TERMINAL_STATUSES = [
        self::STATUS_DISPENSED,
        self::STATUS_CANCELED,
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ISSUED, self::STATUS_CANCELED],
        self::STATUS_ISSUED => [self::STATUS_DISPENSED, self::STATUS_CANCELED],
        self::STATUS_DISPENSED => [],
        self::STATUS_CANCELED => [],
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'dispensed_at' => 'datetime',
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

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(PharmacyDispense::class);
    }
}
