<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const TERMINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_VOID,
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ISSUED, self::STATUS_VOID],
        self::STATUS_ISSUED => [self::STATUS_PARTIALLY_PAID, self::STATUS_PAID, self::STATUS_VOID],
        self::STATUS_PARTIALLY_PAID => [self::STATUS_PARTIALLY_PAID, self::STATUS_PAID, self::STATUS_ISSUED, self::STATUS_VOID],
        self::STATUS_PAID => [self::STATUS_PARTIALLY_PAID, self::STATUS_VOID],
        self::STATUS_VOID => [],
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'balance_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->validateFinancialAmounts();
        });

        static::updating(function (Invoice $invoice) {
            if ($invoice->isDirty(['total_amount', 'paid_amount', 'balance_amount', 'subtotal_amount', 'discount_amount', 'tax_amount'])) {
                $invoice->validateFinancialAmounts();
            }
        });
    }

    public function validateFinancialAmounts(): void
    {
        if ((float) $this->subtotal_amount < 0) {
            throw new \InvalidArgumentException('Invoice subtotal_amount cannot be negative.');
        }

        if ((float) $this->discount_amount < 0) {
            throw new \InvalidArgumentException('Invoice discount_amount cannot be negative.');
        }

        if ((float) $this->tax_amount < 0) {
            throw new \InvalidArgumentException('Invoice tax_amount cannot be negative.');
        }

        if ((float) $this->discount_amount > (float) $this->subtotal_amount) {
            throw new \InvalidArgumentException('Invoice discount_amount cannot exceed subtotal_amount.');
        }

        if ((float) $this->total_amount < 0) {
            throw new \InvalidArgumentException('Invoice total_amount cannot be negative.');
        }

        if ((float) $this->paid_amount < 0) {
            throw new \InvalidArgumentException('Invoice paid_amount cannot be negative.');
        }

        if ((float) $this->balance_amount < 0) {
            throw new \InvalidArgumentException('Invoice balance_amount cannot be negative.');
        }

        if ((float) $this->paid_amount > (float) $this->total_amount) {
            throw new \InvalidArgumentException('Invoice paid_amount cannot exceed total_amount.');
        }

        if ((float) $this->balance_amount > (float) $this->total_amount) {
            throw new \InvalidArgumentException('Invoice balance_amount cannot exceed total_amount.');
        }
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
