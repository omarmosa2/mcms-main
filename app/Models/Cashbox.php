<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\CashboxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cashbox extends BaseModel
{
    /** @use HasFactory<CashboxFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'box_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public static function getTodayBox(int $clinicId): ?self
    {
        return self::query()
            ->forClinic($clinicId)
            ->where('box_date', now()->toDateString())
            ->first();
    }

    public static function hasOpenBox(int $clinicId): bool
    {
        return self::query()
            ->forClinic($clinicId)
            ->where('box_date', now()->toDateString())
            ->where('status', self::STATUS_OPEN)
            ->exists();
    }
}
