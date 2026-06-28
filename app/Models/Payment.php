<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_VOIDED = 'voided';

    public const TERMINAL_STATUSES = [
        self::STATUS_VOIDED,
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_RECORDED => [self::STATUS_REFUNDED, self::STATUS_VOIDED],
        self::STATUS_REFUNDED => [self::STATUS_REFUNDED, self::STATUS_VOIDED],
        self::STATUS_VOIDED => [],
    ];

    protected $attributes = [
        'status' => self::STATUS_RECORDED,
        'refund_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->validateFinancialAmounts();
        });

        static::updating(function (Payment $payment) {
            if ($payment->isDirty(['amount', 'refund_amount'])) {
                $payment->validateFinancialAmounts();
            }
        });
    }

    public function validateFinancialAmounts(): void
    {
        if ((float) $this->amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive.');
        }

        if ((float) $this->refund_amount < 0) {
            throw new \InvalidArgumentException('Payment refund_amount cannot be negative.');
        }

        if ((float) $this->refund_amount > (float) $this->amount) {
            throw new \InvalidArgumentException('Payment refund_amount cannot exceed amount.');
        }
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
