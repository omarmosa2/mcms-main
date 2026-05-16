<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_settings_requires_authentication(): void
    {
        $response = $this->get(route('notifications.edit'));
        $response->assertRedirect(route('login'));
    }

    public function test_notification_update_requires_authentication(): void
    {
        $response = $this->put(route('notifications.update'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_notification_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('notifications.edit'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Notifications')
            ->has('preferences')
            ->has('defaultPreferences'));
    }

    public function test_user_default_preferences(): void
    {
        $user = User::factory()->create();

        $defaults = $user->getDefaultNotificationPreferences();

        $this->assertArrayHasKey('appointment_reminder', $defaults);
        $this->assertArrayHasKey('invoice_issued', $defaults);
        $this->assertArrayHasKey('prescription_ready', $defaults);

        $this->assertTrue($defaults['appointment_reminder']['email']);
        $this->assertFalse($defaults['appointment_reminder']['sms']);
    }

    public function test_user_prefers_email_notification(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'appointment_reminder' => ['email' => true, 'sms' => false],
            ],
        ]);

        $this->assertTrue($user->prefersEmailNotification('appointment_reminder'));
        $this->assertFalse($user->prefersSmsNotification('appointment_reminder'));
    }
}
