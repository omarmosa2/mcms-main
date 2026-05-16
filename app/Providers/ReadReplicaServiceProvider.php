<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class ReadReplicaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->isReadReplicaEnabled()) {
            $this->configureReadReplicaRouting();
        }
    }

    private function isReadReplicaEnabled(): bool
    {
        return env('DB_REPLICA_ENABLED', false)
            && Config::get('database.connections.mysql_read');
    }

    private function configureReadReplicaRouting(): void
    {
        DB::listen(function ($event) {
            if ($this->shouldLogQuery($event)) {
                $connectionName = $event->connectionName;
                $isReadQuery = $this->isReadQuery($event->sql);

                $target = $isReadQuery ? 'mysql_read' : 'mysql';

                if ($connectionName === 'mysql' && $target === 'mysql_read') {
                    $this->logQueryRoute($event, $target);
                }
            }
        });
    }

    private function shouldLogQuery($event): bool
    {
        return Config::get('database.replica.log_queries', false);
    }

    private function isReadQuery(string $sql): bool
    {
        $sql = strtoupper(trim($sql));

        return str_starts_with($sql, 'SELECT');
    }

    private function logQueryRoute($event, string $target): void
    {
        $readable = [
            'select' => 'READ replica',
            'insert' => 'WRITE primary',
            'update' => 'WRITE primary',
            'delete' => 'WRITE primary',
        ];

        $type = 'SELECT' ? 'read' : 'write';

        logger()->debug("Query routed to {$target}", [
            'sql' => $event->sql,
            'connection' => $event->connectionName,
            'target' => $target,
            'time' => $event->time,
        ]);
    }
}
