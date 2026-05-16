<?php

namespace App\Database\Traits;

use Illuminate\Database\Eloquent\Builder;

trait UsesReadReplica
{
    public static function onReadReplica(): Builder
    {
        if (! env('DB_REPLICA_ENABLED', false)) {
            return static::query();
        }

        return static::query()->connection('mysql_read');
    }

    public function onReadReplica(): self
    {
        if (! env('DB_REPLICA_ENABLED', false)) {
            return $this;
        }

        return $this->setConnection('mysql_read');
    }
}
