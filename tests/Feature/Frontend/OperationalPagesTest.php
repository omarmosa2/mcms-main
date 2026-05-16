<?php

namespace Tests\Feature\Frontend;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_index_routes_render_inertia_pages_for_html_requests(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $this->get(route('patients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('patients/Index')
                ->has('patients.data')
                ->has('filters'));

        $this->get(route('departments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('departments/Index')
                ->has('departments.data')
                ->has('filters'));

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('doctors/Index')
                ->has('doctor_profiles.data')
                ->has('doctors')
                ->has('departments')
                ->has('status_options')
                ->has('filters'));

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('appointments/Index')
                ->has('appointments.data')
                ->has('patients')
                ->has('doctors')
                ->has('status_options'));

        $this->get(route('queue.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/Index')
                ->has('queue_entries.data')
                ->has('patients')
                ->has('appointments')
                ->has('doctors')
                ->has('status_options'));

        $this->get(route('visits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('visits/Index')
                ->has('visits.data')
                ->has('patients')
                ->has('appointments')
                ->has('queue_entries')
                ->has('doctors')
                ->has('status_options'));

        $this->get(route('billing.invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/Index')
                ->has('invoices.data')
                ->has('patients')
                ->has('appointments')
                ->has('visits')
                ->has('status_options')
                ->has('payment_method_options'));
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
