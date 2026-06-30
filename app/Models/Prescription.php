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

    public const STATUS_SENT_TO_PHARMACY = 'sent_to_pharmacy';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_PARTIALLY_DISPENSED = 'partially_dispensed';

    public const STATUS_CANCELED = 'canceled';

    public const TERMINAL_STATUSES = [
        self::STATUS_DISPENSED,
        self::STATUS_CANCELED,
    ];

    public const PHARMACY_STATUSES = [
        self::STATUS_SENT_TO_PHARMACY,
        self::STATUS_RECEIVED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_DISPENSED,
        self::STATUS_PARTIALLY_DISPENSED,
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ISSUED, self::STATUS_CANCELED],
        self::STATUS_ISSUED => [self::STATUS_SENT_TO_PHARMACY, self::STATUS_CANCELED],
        self::STATUS_SENT_TO_PHARMACY => [self::STATUS_RECEIVED, self::STATUS_CANCELED],
        self::STATUS_RECEIVED => [self::STATUS_PREPARING, self::STATUS_CANCELED],
        self::STATUS_PREPARING => [self::STATUS_READY, self::STATUS_DISPENSED, self::STATUS_PARTIALLY_DISPENSED, self::STATUS_CANCELED],
        self::STATUS_READY => [self::STATUS_DISPENSED, self::STATUS_PARTIALLY_DISPENSED, self::STATUS_CANCELED],
        self::STATUS_DISPENSED => [],
        self::STATUS_PARTIALLY_DISPENSED => [self::STATUS_DISPENSED],
        self::STATUS_CANCELED => [],
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'sent_to_pharmacy_at' => 'datetime',
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

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(PharmacyDispense::class);
    }

    public function isPharmacyPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT_TO_PHARMACY,
            self::STATUS_RECEIVED,
            self::STATUS_PREPARING,
            self::STATUS_READY,
        ], true);
    }

    public function transitionTo(string $newStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return false;
        }

        $this->status = $newStatus;

        if ($newStatus === self::STATUS_SENT_TO_PHARMACY) {
            $this->sent_to_pharmacy_at = now();
        }

        if ($newStatus === self::STATUS_DISPENSED) {
            $this->dispensed_at = now();
        }

        return $this->save();
    }
}
