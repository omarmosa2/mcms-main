<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class QueueNumberSequence extends BaseModel
{
    use SoftDeletes;

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
        return DB::transaction(function () {
            $this->lockForUpdate()->find($this->id);
            $this->increment('current_value');

            return (int) $this->current_value;
        });
    }

    public static function getNextValue(int $clinicId, string $queueDate): int
    {
        $normalizedDate = is_string($queueDate) && strlen($queueDate) > 10 ? substr($queueDate, 0, 10) : $queueDate;

        return DB::transaction(function () use ($clinicId, $normalizedDate): int {
            $maxQueueNumber = (int) DB::table('queue_entries')
                ->where('clinic_id', $clinicId)
                ->whereDate('queue_date', $normalizedDate)
                ->max('queue_number');

            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                return static::getNextValueSqlite($clinicId, $normalizedDate, $maxQueueNumber);
            }

            return static::getNextValueMysql($clinicId, $normalizedDate, $maxQueueNumber);
        });
    }

    private static function getNextValueSqlite(int $clinicId, string $normalizedDate, int $maxQueueNumber): int
    {
        DB::table('queue_number_seq')
            ->updateOrInsert(
                ['clinic_id' => $clinicId, 'queue_date' => $normalizedDate],
                ['current_value' => DB::raw("COALESCE((SELECT current_value FROM queue_number_seq WHERE clinic_id = {$clinicId} AND queue_date = '{$normalizedDate}'), {$maxQueueNumber}) + 1"), 'updated_at' => now()],
            );

        $value = (int) DB::table('queue_number_seq')
            ->where('clinic_id', $clinicId)
            ->where('queue_date', $normalizedDate)
            ->value('current_value');

        return $value;
    }

    private static function getNextValueMysql(int $clinicId, string $normalizedDate, int $maxQueueNumber): int
    {
        $sequence = static::query()
            ->forClinic($clinicId)
            ->where('queue_date', $normalizedDate)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = static::query()->create([
                'clinic_id' => $clinicId,
                'queue_date' => $normalizedDate,
                'current_value' => $maxQueueNumber,
            ]);
        }

        $sequence->increment('current_value');

        return (int) $sequence->current_value;
    }
}
