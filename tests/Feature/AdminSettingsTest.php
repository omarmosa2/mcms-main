<?php

namespace Tests\Feature;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $clinic = Clinic::query()->firstOrCreate(
            ['code' => 'TEST001'],
            [
                'name' => 'Test Clinic',
                'legal_name' => 'Test Clinic LLC',
                'timezone' => 'Asia/Riyadh',
                'currency' => 'SAR',
                'phone' => '000-000-0000',
                'email' => 'test@example.com',
                'address' => 'Test Address',
                'is_active' => true,
            ],
        );

        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Test Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        app(AssignUserRoleAction::class)->handle($admin, 'admin', null);

        return $admin;
    }

    public function test_clinic_settings_requires_authentication(): void
    {
        $response = $this->get(route('admin-settings.clinic'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_view_clinic_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.clinic'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/ClinicSettings')
            ->has('settings'));
    }

    public function test_admin_can_update_clinic_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->put(route('admin-settings.clinic.update'), [
            'name' => 'Updated Clinic Name',
            'director_name' => 'Dr. Test',
            'phone' => '123456789',
            'email' => 'clinic@test.com',
            'address' => '123 Test St',
            'decimal_places' => 2,
            'thousands_separator' => ',',
        ]);

        $response->assertRedirect(route('admin-settings.clinic'));
    }

    public function test_admin_can_view_appointment_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.appointments'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/AppointmentSettings')
            ->has('settings'));
    }

    public function test_admin_can_update_appointment_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->put(route('admin-settings.appointments.update'), [
            'default_duration' => 30,
            'allow_outside_hours' => false,
            'allow_overlapping' => false,
            'max_per_doctor_per_day' => 25,
            'types' => [
                ['name' => 'فحص أولي', 'is_default' => true],
                ['name' => 'مراجعة', 'is_default' => true],
            ],
        ]);

        $response->assertRedirect(route('admin-settings.appointments'));
    }

    public function test_admin_can_view_financial_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.financial'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/FinancialSettings')
            ->has('settings'));
    }

    public function test_admin_can_view_permissions_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.permissions'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/PermissionsSettings')
            ->has('roles')
            ->has('allPermissions'));
    }

    public function test_admin_can_view_appearance_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.appearance'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/AdminAppearance')
            ->has('settings'));
    }

    public function test_admin_can_view_security_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.security'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/SecuritySettings')
            ->has('activityLogs'));
    }

    public function test_admin_can_view_diagnostics(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.diagnostics'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/DiagnosticsSettings')
            ->has('diagnostics')
            ->has('diagnostics.database')
            ->has('diagnostics.application')
            ->has('diagnostics.performance'));
    }

    public function test_admin_can_view_support_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $response = $this->get(route('admin-settings.support'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/admin/SupportSettings')
            ->has('settings'));
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $clinic = Clinic::query()->firstOrCreate(
            ['code' => 'TEST002'],
            [
                'name' => 'Test Clinic 2',
                'legal_name' => 'Test Clinic 2 LLC',
                'timezone' => 'Asia/Riyadh',
                'currency' => 'SAR',
                'is_active' => true,
            ],
        );

        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $receptionist = User::query()->updateOrCreate(
            ['email' => 'receptionist@test.com'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Test Receptionist',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        app(AssignUserRoleAction::class)->handle($receptionist, 'receptionist', null);

        $this->actingAs($receptionist);

        $response = $this->get(route('admin-settings.clinic'));
        $response->assertForbidden();
    }
}
