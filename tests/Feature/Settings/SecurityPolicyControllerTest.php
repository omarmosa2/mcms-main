<?php

namespace Tests\Feature\Settings;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPolicyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_can_fetch_and_update_security_policy(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $fetchResponse = $this->getJson(route('security-policies.show'));
        $fetchResponse->assertOk();
        $fetchResponse->assertJsonPath('data.password_min_length', 12);

        $updateResponse = $this->putJson(route('security-policies.update'), [
            'password_min_length' => 14,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => true,
            'session_lifetime_minutes' => 90,
            'idle_timeout_minutes' => 20,
            'force_two_factor' => true,
            'confirm_password_for_security_actions' => true,
            'audit_retention_days' => 400,
            'sensitive_access_retention_days' => 390,
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.force_two_factor', true);

        $this->assertDatabaseHas('security_policies', [
            'clinic_id' => $clinic->id,
            'updated_by' => $user->id,
            'password_min_length' => 14,
            'force_two_factor' => true,
        ]);
    }

    public function test_receptionist_cannot_manage_security_policies(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $this->getJson(route('security-policies.show'))->assertForbidden();
        $this->putJson(route('security-policies.update'), [
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
        ])->assertForbidden();
    }

    public function test_clinic_admin_can_create_user_invitation(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->postJson(route('security-invitations.store'), [
            'email' => 'invited.staff@example.com',
            'full_name' => 'Invited Staff',
            'role_name' => 'receptionist',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'invited.staff@example.com');

        $this->assertDatabaseHas('user_invitations', [
            'clinic_id' => $clinic->id,
            'email' => 'invited.staff@example.com',
            'role_name' => 'receptionist',
            'accepted_at' => null,
        ]);

        $invitation = UserInvitation::query()->firstOrFail();
        $this->assertNotNull($invitation->token);
    }

    public function test_clinic_admin_can_view_compliance_cockpit(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->get(route('compliance.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Compliance')
            ->has('kpis')
            ->has('recent_audit_events')
            ->has('recent_sensitive_access')
            ->has('recent_runs'));
    }

    public function test_receptionist_cannot_view_compliance_cockpit(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $this->get(route('compliance.index'))->assertForbidden();
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
