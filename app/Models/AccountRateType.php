<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountRateType extends BaseModel
{
    use SoftDeletes;

    public const TYPE_TAX = 'tax';

    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'rate_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function calculate(float $amount): float
    {
        return round($amount * $this->rate_percentage / 100, 2);
    }
}
