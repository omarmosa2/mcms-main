<?php

namespace Tests\Feature\Console;

use App\Models\Clinic;
use App\Models\ComplianceRun;
use App\Models\SecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OperationalCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_command_passes(): void
    {
        $this->artisan('system:health')
            ->expectsOutputToContain('System health status: PASS')
            ->assertSuccessful();
    }

    public function test_backup_create_and_verify_commands_succeed_with_sqlite(): void
    {
        File::deleteDirectory(storage_path('app/backups'));

        $this->artisan('backup:create')->assertSuccessful();
        $this->artisan('backup:verify')->assertSuccessful();

        $this->assertDatabaseHas('compliance_runs', [
            'run_type' => 'backup.create',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('compliance_runs', [
            'run_type' => 'backup.verify',
            'status' => 'completed',
        ]);
    }

    public function test_compliance_report_and_purge_commands_record_runs(): void
    {
        $clinic = Clinic::factory()->create();

        SecurityPolicy::query()->create([
            'clinic_id' => $clinic->id,
            'updated_by' => null,
            'password_min_length' => 12,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'session_lifetime_minutes' => 120,
            'idle_timeout_minutes' => 30,
            'force_two_factor' => false,
            'confirm_password_for_security_actions' => true,
            'audit_retention_days' => 365,
            'sensitive_access_retention_days' => 365,
        ]);

        $this->artisan('compliance:report')->assertSuccessful();
        $this->artisan('compliance:purge --dry-run')->assertSuccessful();

        $this->assertTrue(ComplianceRun::query()->where('run_type', 'compliance.report')->exists());
        $this->assertTrue(ComplianceRun::query()->where('run_type', 'compliance.purge')->exists());
    }
}
