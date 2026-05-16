<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;

class DatabaseService
{
    public function isReplicaEnabled(): bool
    {
        return config('database.replica.enabled', false);
    }

    public function useReadConnection(): void
    {
        if (! $this->isReplicaEnabled()) {
            return;
        }

        $connection = DB::connection();

        if (method_exists($connection, 'pdo')) {
            $connection->pdo('read');
        }
    }

    public function useWriteConnection(): void
    {
        $connection = DB::connection();

        if (method_exists($connection, 'pdo')) {
            $connection->pdo('write');
        }
    }

    public function forceWriteForSeconds(int $seconds): void
    {
        if (request() !== null && request()->hasSession()) {
            request()->session()->put('last_write_at', now()->addSeconds($seconds));
        }
    }

    public function shouldForceWrite(): bool
    {
        if (request() === null || ! request()->hasSession()) {
            return false;
        }

        $lastWriteAt = request()->session()->get('last_write_at');

        if ($lastWriteAt === null) {
            return false;
        }

        return now()->lessThan($lastWriteAt);
    }
}
