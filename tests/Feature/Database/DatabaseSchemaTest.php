<?php

namespace Tests\Feature\Database;

use App\Models\DoctorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcms_core_tables_exist(): void
    {
        $tables = [
            'clinics',
            'patients',
            'appointments',
            'queue_entries',
            'visits',
            'invoices',
            'invoice_items',
            'payments',
            'patient_chronic_conditions',
            'patient_allergies',
            'patient_medications',
            'patient_attachments',
            'departments',
            'doctor_profiles',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_domain_tables_are_scoped_by_clinic_id(): void
    {
        $tables = [
            'users',
            'patients',
            'appointments',
            'queue_entries',
            'visits',
            'invoices',
            'invoice_items',
            'payments',
            'patient_chronic_conditions',
            'patient_allergies',
            'patient_medications',
            'patient_attachments',
            'departments',
            'doctor_profiles',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'clinic_id'),
                "Expected table [{$table}] to contain [clinic_id]."
            );
        }
    }

    public function test_workflow_status_columns_exist_for_core_modules(): void
    {
        $this->assertTrue(Schema::hasColumn('appointments', 'status'));
        $this->assertTrue(Schema::hasColumn('visits', 'status'));
        $this->assertTrue(Schema::hasColumn('invoices', 'status'));

        $this->assertTrue(Schema::hasColumn('payments', 'status'));
        $this->assertTrue(Schema::hasColumn('queue_entries', 'status'));
        $this->assertTrue(Schema::hasColumn('doctor_profiles', 'is_active'));
    }

    public function test_doctor_profile_factory_generates_clinic_scoped_relations(): void
    {
        $doctorProfile = DoctorProfile::factory()->create();

        $this->assertSame($doctorProfile->clinic_id, $doctorProfile->user?->clinic_id);
        $this->assertNotNull($doctorProfile->clinic);
    }
}
