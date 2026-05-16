<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends BaseModel
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'line_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InvoiceItem $item): void {
            $item->validateFinancialAmounts();
        });

        static::updating(function (InvoiceItem $item): void {
            if ($item->isDirty(['quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total'])) {
                $item->validateFinancialAmounts();
            }
        });
    }

    public function validateFinancialAmounts(): void
    {
        if ((float) $this->quantity <= 0) {
            throw new \InvalidArgumentException('Invoice item quantity must be positive.');
        }

        if ((float) $this->unit_price < 0) {
            throw new \InvalidArgumentException('Invoice item unit_price cannot be negative.');
        }

        if ((float) $this->discount_amount < 0) {
            throw new \InvalidArgumentException('Invoice item discount_amount cannot be negative.');
        }

        if ((float) $this->tax_amount < 0) {
            throw new \InvalidArgumentException('Invoice item tax_amount cannot be negative.');
        }

        if ((float) $this->line_total < 0) {
            throw new \InvalidArgumentException('Invoice item line_total cannot be negative.');
        }

        $maxDiscount = (float) $this->quantity * (float) $this->unit_price;

        if ((float) $this->discount_amount > $maxDiscount) {
            throw new \InvalidArgumentException('Invoice item discount_amount cannot exceed quantity multiplied by unit_price.');
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
}
