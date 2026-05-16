<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('system:health {--json : Output health status as JSON}')]
#[Description('Run baseline production-readiness health checks')]
class SystemHealthCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [
            'database_connection' => $this->checkDatabaseConnection(),
            'jobs_table_exists' => Schema::hasTable('jobs'),
            'storage_writable' => is_writable(storage_path('app')),
            'logs_writable' => is_writable(storage_path('logs')),
            'queue_driver_configured' => config('queue.default') !== null,
        ];

        $isHealthy = collect($checks)->every(fn (bool $status): bool => $status === true);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'healthy' => $isHealthy,
                'checks' => $checks,
                'checked_at' => now()->toISOString(),
            ], JSON_PRETTY_PRINT));

            return $isHealthy ? self::SUCCESS : self::FAILURE;
        }

        $rows = collect($checks)
            ->map(fn (bool $status, string $name): array => [
                $name,
                $status ? 'PASS' : 'FAIL',
            ])
            ->values()
            ->all();

        $this->table(['Check', 'Status'], $rows);

        $this->line($isHealthy
            ? 'System health status: PASS'
            : 'System health status: FAIL');

        return $isHealthy ? self::SUCCESS : self::FAILURE;
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
