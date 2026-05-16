<?php

namespace Tests\Feature\Frontend;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EnterpriseSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_still_renders_when_enterprise_tables_are_missing(): void
    {
        $clinic = Clinic::factory()->create();

        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'email_verified_at' => now(),
        ]);

        app(AssignUserRoleAction::class)->handle($user, 'clinic_admin');

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('branding_settings');
        Schema::dropIfExists('security_policies');
        Schema::enableForeignKeyConstraints();

        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('branding.company_name', null)
                ->where('security.policy', null));
    }
}
