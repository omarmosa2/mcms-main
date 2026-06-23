<?php

namespace Tests\Feature\Security;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_user(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->postJson(route('users.store'), [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role_name' => 'receptionist',
            'password' => 'securepassword123',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'John Doe');
        $response->assertJsonPath('data.email', 'john.doe@example.com');

        $userId = (int) $response->json('data.id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'clinic_id' => $clinic->id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('role_user', [
            'clinic_id' => $clinic->id,
            'user_id' => $userId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'users.create',
        ]);
    }

    public function test_show_user(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $targetUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Jane Smith',
        ]);

        app(AssignUserRoleAction::class)->handle($targetUser, 'receptionist');

        $response = $this->getJson(route('users.index', ['search' => 'Jane Smith']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Jane Smith');
    }

    public function test_index_includes_doctor_accounts_from_all_clinics_and_allows_searching_by_username(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $doctorClinic = Clinic::factory()->create([
            'name' => 'Doctor Clinic',
        ]);

        $doctorAccount = User::factory()->create([
            'clinic_id' => $doctorClinic->id,
            'name' => 'Dr. Account',
            'username' => 'doctoraccount',
        ]);
        app(AssignUserRoleAction::class)->handle($doctorAccount, 'doctor');

        $response = $this->getJson(route('users.index', ['search' => 'doctoraccount']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $doctorAccount->id);
        $response->assertJsonPath('data.0.username', 'doctoraccount');
        $response->assertJsonPath('data.0.clinic.name', 'Doctor Clinic');
        $response->assertJsonPath('data.0.role_names.0', 'doctor');
    }

    public function test_update_user(): void
    {
        $clinic = Clinic::factory()->create();
        $adminUser = $this->authenticateForClinic($clinic, 'clinic_admin');

        $targetUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        app(AssignUserRoleAction::class)->handle($targetUser, 'receptionist');

        $response = $this->putJson(route('users.update', ['userId' => $targetUser->id]), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $adminUser->id,
            'action' => 'users.update',
        ]);
    }

    public function test_delete_user(): void
    {
        $clinic = Clinic::factory()->create();
        $adminUser = $this->authenticateForClinic($clinic, 'clinic_admin');

        $targetUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'User To Delete',
        ]);

        app(AssignUserRoleAction::class)->handle($targetUser, 'receptionist');

        $response = $this->deleteJson(route('users.destroy', ['userId' => $targetUser->id]));

        $response->assertNoContent();

        $this->assertSoftDeleted($targetUser);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $adminUser->id,
            'action' => 'users.delete',
        ]);
    }

    public function test_bulk_destroy_users(): void
    {
        $clinic = Clinic::factory()->create();
        $adminUser = $this->authenticateForClinic($clinic, 'clinic_admin');

        $deletableUser = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($deletableUser, 'receptionist');

        $response = $this->deleteJson(route('users.bulk-destroy'), [
            'ids' => [$deletableUser->id, $adminUser->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_ids', [$deletableUser->id]);
        $response->assertJsonPath('data.failed_ids', [$adminUser->id]);

        $this->assertSoftDeleted($deletableUser);
        $this->assertDatabaseHas('users', [
            'id' => $adminUser->id,
            'deleted_at' => null,
        ]);
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
