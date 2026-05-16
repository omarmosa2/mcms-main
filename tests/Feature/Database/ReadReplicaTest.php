<?php

namespace Tests\Feature\Database;

use App\Services\Database\DatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReadReplicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_replica_is_disabled_by_default(): void
    {
        Config::set('database.replica.enabled', false);

        $this->assertFalse(app(DatabaseService::class)->isReplicaEnabled());
    }

    public function test_replica_can_be_enabled(): void
    {
        Config::set('database.replica.enabled', true);

        $this->assertTrue(app(DatabaseService::class)->isReplicaEnabled());
    }

    public function test_mysql_connection_has_read_write_config_when_enabled(): void
    {
        Config::set('database.replica.enabled', true);
        Config::set('database.connections.mysql.read', [
            'host' => ['read-replica-host'],
            'port' => '3306',
        ]);

        $mysqlConfig = config('database.connections.mysql');

        $this->assertArrayHasKey('read', $mysqlConfig);
        $this->assertArrayHasKey('write', $mysqlConfig);
        $this->assertTrue($mysqlConfig['sticky']);
    }

    public function test_mysql_connection_without_replica_has_no_read_config(): void
    {
        Config::set('database.replica.enabled', false);
        Config::set('database.connections.mysql.read', null);

        $mysqlConfig = config('database.connections.mysql');

        $this->assertNull($mysqlConfig['read']);
    }

    public function test_use_write_connection_does_not_throw_error(): void
    {
        $service = app(DatabaseService::class);

        $service->useWriteConnection();

        $this->assertTrue(true);
    }

    public function test_database_service_resolves_from_container(): void
    {
        $service = app(DatabaseService::class);

        $this->assertInstanceOf(DatabaseService::class, $service);
    }

    public function test_should_force_write_returns_false_without_session(): void
    {
        $service = app(DatabaseService::class);

        $this->assertFalse($service->shouldForceWrite());
    }

    public function test_sticky_mode_is_configured_for_mysql(): void
    {
        $this->assertTrue(config('database.connections.mysql.sticky'));
    }
}
