<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class QueueNumberSequence extends BaseModel
{
    protected $table = 'queue_number_seq';

    protected $fillable = [
        'clinic_id',
        'queue_date',
        'current_value',
    ];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'current_value' => 'integer',
        ];
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function incrementAndGet(): int
    {
        return DB::transaction(function (): int {
            $sequence = static::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->current_value = (int) $sequence->current_value + 1;
            $sequence->save();

            $this->setRawAttributes($sequence->getAttributes(), true);

            return (int) $sequence->current_value;
        });
    }

    public static function getNextValue(int $clinicId, string $queueDate): int
    {
        $normalizedDate = is_string($queueDate) && strlen($queueDate) > 10 ? substr($queueDate, 0, 10) : $queueDate;

        return DB::transaction(function () use ($clinicId, $normalizedDate): int {
            $sequence = static::query()
                ->forClinic($clinicId)
                ->whereDate('queue_date', $normalizedDate)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = static::createSequenceForDate($clinicId, $normalizedDate);
            }

            $sequence->current_value = (int) $sequence->current_value + 1;
            $sequence->save();

            return (int) $sequence->current_value;
        });
    }

    private static function createSequenceForDate(int $clinicId, string $normalizedDate): self
    {
        $maxQueueNumber = static::existingMaxQueueNumber($clinicId, $normalizedDate);

        try {
            return static::query()->create([
                'clinic_id' => $clinicId,
                'queue_date' => $normalizedDate,
                'current_value' => $maxQueueNumber,
            ]);
        } catch (QueryException $e) {
            $sequence = static::query()
                ->forClinic($clinicId)
                ->whereDate('queue_date', $normalizedDate)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw $e;
            }

            return $sequence;
        }
    }

    private static function existingMaxQueueNumber(int $clinicId, string $normalizedDate): int
    {
        return (int) DB::table('queue_entries')
            ->where('clinic_id', $clinicId)
            ->whereDate('queue_date', $normalizedDate)
            ->max('queue_number');
    }
}
