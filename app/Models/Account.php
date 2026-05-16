<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends BaseModel
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function getBalanceAttribute(): float
    {
        $debitSum = $this->journalLines()->sum('debit');
        $creditSum = $this->journalLines()->sum('credit');

        return match ($this->type) {
            self::TYPE_ASSET, self::TYPE_EXPENSE => $this->opening_balance + $debitSum - $creditSum,
            self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_REVENUE => $this->opening_balance + $creditSum - $debitSum,
            default => 0,
        };
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE]);
    }

    public function isCreditNormal(): bool
    {
        return in_array($this->type, [self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_REVENUE]);
    }
}
