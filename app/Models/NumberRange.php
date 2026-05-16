<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NumberRange extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public static function getForEntity(int $clinicId, string $entityType): ?self
    {
        return static::query()
            ->where('clinic_id', $clinicId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->first();
    }

    public function generateSequence(): int
    {
        return ++$this->current_sequence;
    }

    public function formatNumber(int $sequence): string
    {
        $date = now()->format('Ymd');
        $pattern = str_replace('PREFIX', $this->prefix, $this->format_pattern);
        $pattern = str_replace('YYYYMMDD', $date, $pattern);
        $pattern = str_replace('0000', str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), $pattern);
        $pattern = str_replace('000', str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), $pattern);

        return $pattern;
    }
}
