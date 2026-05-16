<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadReplicaConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_connection_has_read_write_structure(): void
    {
        $config = config('database.connections.mysql');

        $this->assertArrayHasKey('read', $config);
        $this->assertArrayHasKey('write', $config);
    }

    public function test_replica_enabled_flag_is_configurable(): void
    {
        $this->assertArrayHasKey('replica', config('database'));
        $this->assertArrayHasKey('enabled', config('database.replica'));
    }

    public function test_redis_read_connection_configured(): void
    {
        $this->assertArrayHasKey('cache_read', config('database.redis'));
    }

    public function test_default_connection_still_works(): void
    {
        $defaultConnection = config('database.default');

        $this->assertNotEmpty($defaultConnection);
        $this->assertArrayHasKey($defaultConnection, config('database.connections'));
    }

    public function test_read_replica_environment_variables_documented(): void
    {
        $envVars = [
            'DB_REPLICA_ENABLED',
            'DB_READ_HOST',
            'DB_READ_PORT',
            'DB_READ_USERNAME',
            'DB_READ_PASSWORD',
        ];

        $envExample = file_get_contents(base_path('.env.example'));

        foreach ($envVars as $var) {
            $this->assertStringContainsString($var, $envExample, "{$var} should be documented in .env.example");
        }
    }

    public function test_mysql_connection_has_sticky_mode(): void
    {
        $this->assertTrue(config('database.connections.mysql.sticky'));
    }

    public function test_mysql_write_connection_has_correct_structure(): void
    {
        $config = config('database.connections.mysql.write');

        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('database', $config);
        $this->assertArrayHasKey('username', $config);
        $this->assertArrayHasKey('password', $config);
    }
}
