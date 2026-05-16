<?php

namespace Tests\Feature\Auth;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register_when_public_registration_is_enabled()
    {
        config()->set('security.public_registration_enabled', true);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_invitation_when_public_registration_is_disabled(): void
    {
        config()->set('security.public_registration_enabled', false);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertSessionHasErrors('invitation_token');
        $this->assertGuest();
    }

    public function test_invited_users_can_register_and_receive_invited_role(): void
    {
        config()->set('security.public_registration_enabled', false);

        $clinic = Clinic::factory()->create();
        $inviter = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($inviter, 'clinic_admin');

        $invitation = UserInvitation::query()->create([
            'clinic_id' => $clinic->id,
            'invited_by' => $inviter->id,
            'accepted_user_id' => null,
            'email' => 'invited.user@example.com',
            'full_name' => 'Invited User',
            'role_name' => 'receptionist',
            'token' => 'token-abc-123',
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'metadata' => null,
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Invited User',
            'email' => 'invited.user@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'invitation_token' => 'token-abc-123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        /** @var User $registeredUser */
        $registeredUser = auth()->user();

        $this->assertSame($clinic->id, $registeredUser->clinic_id);
        $this->assertTrue($registeredUser->hasRole('receptionist'));

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($registeredUser->id, $invitation->accepted_user_id);
    }
}
