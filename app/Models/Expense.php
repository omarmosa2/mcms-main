<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends BaseModel
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_TRANSFER = 'transfer';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if (empty($expense->expense_number)) {
                $expense->expense_number = static::generateExpenseNumber();
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForClinicOrGeneral($query, ?int $clinicId)
    {
        if ($clinicId !== null) {
            return $query->where(function ($q) use ($clinicId) {
                $q->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id');
            });
        }

        return $query;
    }

    public static function generateExpenseNumber(): string
    {
        $year = now()->format('Y');
        $lastExpense = static::withoutGlobalScope('clinic')
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastExpense ? (int) substr($lastExpense->expense_number, -5) : 0;

        return 'EXP-'.$year.'-'.str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'معلق',
            self::STATUS_PAID => 'مدفوع',
            self::STATUS_CANCELLED => 'ملغي',
        ];
    }

    public static function paymentMethodLabels(): array
    {
        return [
            self::PAYMENT_METHOD_CASH => 'نقداً',
            self::PAYMENT_METHOD_TRANSFER => 'تحويل',
            self::PAYMENT_METHOD_CARD => 'بطاقة',
            self::PAYMENT_METHOD_OTHER => 'أخرى',
        ];
    }
}
