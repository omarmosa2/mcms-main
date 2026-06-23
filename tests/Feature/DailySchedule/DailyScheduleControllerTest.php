<?php

namespace Tests\Feature\DailySchedule;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_when_no_doctors_are_registered(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($user, 'clinic_admin');
        $this->actingAs($user);

        $response = $this->get(route('daily-schedule.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('daily-schedule/Index')
            ->has('scheduleData')
            ->has('clinics')
            ->has('doctors'));
    }
}
