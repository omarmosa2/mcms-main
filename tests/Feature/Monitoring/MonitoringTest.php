<?php

namespace Tests\Feature\Monitoring;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson(route('monitoring.health'));

        $response->assertOk();
        $response->assertJsonPath('status', 'healthy');
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database' => ['status', 'duration_ms'],
                'cache' => ['status', 'duration_ms'],
                'disk' => ['status', 'disk_used_percentage'],
            ],
            'timestamp',
        ]);
    }

    public function test_health_check_database_is_healthy(): void
    {
        $response = $this->getJson(route('monitoring.health'));

        $response->assertJsonPath('checks.database.status', 'healthy');
        $response->assertJsonPath('checks.database.connection', 'sqlite');
    }

    public function test_health_check_cache_is_healthy(): void
    {
        $response = $this->getJson(route('monitoring.health'));

        $response->assertJsonPath('checks.cache.status', 'healthy');
    }

    public function test_health_check_disk_is_healthy(): void
    {
        $response = $this->getJson(route('monitoring.health'));

        $response->assertJsonPath('checks.disk.status', 'healthy');
        $response->assertJsonStructure(['checks' => ['disk' => ['disk_free_bytes']]]);
    }

    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get(route('monitoring.metrics'));

        $response->assertOk();
        $this->assertStringContainsString('text/plain; version=0.0.4', $response->headers->get('Content-Type'));
        $content = $response->getContent();

        $this->assertStringContainsString('# HELP mcms_total_patients', $content);
        $this->assertStringContainsString('# TYPE mcms_total_patients gauge', $content);
        $this->assertStringContainsString('mcms_total_patients', $content);
    }

    public function test_metrics_includes_all_expected_metrics(): void
    {
        $response = $this->get(route('monitoring.metrics'));

        $content = $response->getContent();

        $this->assertStringContainsString('mcms_total_patients', $content);
        $this->assertStringContainsString('mcms_active_visits', $content);
        $this->assertStringContainsString('mcms_pending_queue', $content);
        $this->assertStringContainsString('mcms_today_appointments', $content);
        $this->assertStringContainsString('mcms_outstanding_invoices', $content);
        $this->assertStringContainsString('mcms_cache_hits_total', $content);
        $this->assertStringContainsString('mcms_cache_misses_total', $content);
        $this->assertStringContainsString('mcms_app_info', $content);
    }

    public function test_metrics_include_app_info(): void
    {
        $response = $this->get(route('monitoring.metrics'));

        $content = $response->getContent();

        $this->assertStringContainsString('environment="testing"', $content);
    }

    public function test_health_check_is_accessible_without_auth(): void
    {
        $response = $this->getJson(route('monitoring.health'));

        $response->assertOk();
    }

    public function test_metrics_is_accessible_without_auth(): void
    {
        $response = $this->get(route('monitoring.metrics'));

        $response->assertOk();
    }
}
