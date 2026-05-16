<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    public function __construct(
        private int $ttlMinutes = 1440,
    ) {}

    public function getOrCreate(string $key, callable $callback, ?int $clinicId = null, ?string $operationType = null): Model
    {
        return DB::transaction(function () use ($key, $callback, $clinicId, $operationType) {
            $existing = $this->findCompleted($key, $clinicId);

            if ($existing !== null) {
                return $existing;
            }

            return $this->executeWithLock($key, $callback, $clinicId, $operationType);
        });
    }

    private function findCompleted(string $key, ?int $clinicId = null): ?Model
    {
        $query = DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->where('status', 'completed')
            ->where('created_at', '>', now()->subMinutes($this->ttlMinutes));

        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }

        $record = $query->first();

        if ($record === null) {
            return null;
        }

        $data = json_decode($record->response_body, true);

        return (new $data['model_type'])->findOrFail($data['model_id']);
    }

    private function executeWithLock(string $key, callable $callback, ?int $clinicId = null, ?string $operationType = null): Model
    {
        $query = DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->lockForUpdate();

        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }

        $record = $query->first();

        if ($record !== null) {
            if ($record->status === 'completed') {
                return $this->findCompleted($key, $clinicId);
            }

            if ($record->status === 'processing') {
                throw new \RuntimeException('Duplicate request is being processed.');
            }
        }

        $this->createRecord($key, $clinicId, $operationType);

        try {
            $result = $callback();

            $this->completeRecord($key, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->failRecord($key);

            throw $e;
        }
    }

    private function createRecord(string $key, ?int $clinicId = null, ?string $operationType = null): void
    {
        DB::table('idempotency_records')->updateOrInsert(
            ['idempotency_key' => $key],
            [
                'clinic_id' => $clinicId,
                'status' => 'processing',
                'operation_type' => $operationType,
                'response_body' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function completeRecord(string $key, Model $result): void
    {
        DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->update([
                'status' => 'completed',
                'response_body' => json_encode([
                    'model_type' => get_class($result),
                    'model_id' => $result->getKey(),
                ]),
                'updated_at' => now(),
            ]);
    }

    private function failRecord(string $key): void
    {
        DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);
    }
}
