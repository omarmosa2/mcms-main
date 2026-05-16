<?php

namespace Tests\Feature\Rbac;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RbacSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_clinic_bootstraps_system_roles_and_permissions(): void
    {
        $clinic = Clinic::factory()->create();

        $this->assertDatabaseHas('roles', [
            'clinic_id' => $clinic->id,
            'name' => 'super_admin',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('roles', [
            'clinic_id' => $clinic->id,
            'name' => 'clinic_admin',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('roles', [
            'clinic_id' => $clinic->id,
            'name' => 'admin',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('permissions', [
            'clinic_id' => $clinic->id,
            'name' => 'patient.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'clinic_id' => $clinic->id,
            'name' => 'appointments.*',
        ]);
    }

    public function test_user_without_role_cannot_access_permission_protected_route(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson(route('patients.index'));

        $response->assertForbidden();
    }

    public function test_receptionist_can_create_and_view_patients_but_cannot_delete(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'receptionist');

        $storeResponse = $this->postJson(route('patients.store'), [
            'file_number' => 'PT-RBAC-1',
            'first_name' => 'Sara',
            'last_name' => 'Khaled',
            'date_of_birth' => '1994-02-20',
        ]);

        $storeResponse->assertCreated();
        $storeResponse->assertJsonPath('data.file_number', 'PT-RBAC-1');

        $patient = Patient::query()
            ->forClinic($clinic->id)
            ->where('file_number', 'PT-RBAC-1')
            ->firstOrFail();

        $viewResponse = $this->getJson(route('patients.show', ['patientId' => $patient->id]));
        $viewResponse->assertOk();

        $deleteResponse = $this->deleteJson(route('patients.destroy', ['patientId' => $patient->id]));
        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
        ]);

        $this->assertDatabaseHas('role_user', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_receptionist_can_delete_scheduled_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $response = $this->deleteJson(route('appointments.destroy', ['appointmentId' => $appointment->id]));

        $response->assertNoContent();
        $this->assertSoftDeleted($appointment);
    }

    public function test_super_admin_role_has_full_access(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'super_admin');

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-RBAC-2',
        ]);

        $response = $this->deleteJson(route('patients.destroy', ['patientId' => $patient->id]));

        $response->assertNoContent();
        $this->assertSoftDeleted($patient);
    }

    public function test_super_admin_receives_wildcard_permission_in_inertia_payload(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'super_admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.0', '*')
                ->where('auth.roles.0', 'super_admin'));
    }

    public function test_assign_user_role_action_assigns_expected_role(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $role = app(AssignUserRoleAction::class)->handle($user, 'accountant');

        $this->assertInstanceOf(Role::class, $role);

        $this->assertDatabaseHas('role_user', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('permissions', [
            'clinic_id' => $clinic->id,
            'name' => 'payment.record',
        ]);

        $this->assertTrue(
            Permission::query()
                ->forClinic($clinic->id)
                ->where('name', 'payment.record')
                ->exists(),
        );
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
