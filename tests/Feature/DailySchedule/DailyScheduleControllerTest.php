<?php

namespace Tests\Feature\DailySchedule;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
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

    public function test_index_lists_only_active_clinic_doctors_available_on_selected_day(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Open Clinic', 'is_active' => true]);
        $otherClinic = Clinic::factory()->create(['name' => 'Other Clinic', 'is_active' => true]);
        $inactiveClinic = Clinic::factory()->create(['name' => 'Inactive Clinic', 'is_active' => false]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($user, 'clinic_admin');
        $this->actingAs($user);

        $selectedDate = '2026-06-29';
        $selectedDay = 1;

        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $selectedDay,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        ClinicWorkingHour::query()->create([
            'clinic_id' => $otherClinic->id,
            'day_of_week' => $selectedDay,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        ClinicWorkingHour::query()->create([
            'clinic_id' => $inactiveClinic->id,
            'day_of_week' => $selectedDay,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Clinic Doctor']);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);
        $otherDoctorUser = User::factory()->create(['clinic_id' => $otherClinic->id, 'name' => 'Other Doctor']);
        $otherDoctor = DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctorUser->id,
            'is_active' => true,
        ]);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => $selectedDay,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $otherClinic->id,
            'doctor_profile_id' => $otherDoctor->id,
            'day_of_week' => $selectedDay,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->get(route('daily-schedule.index', [
            'date' => $selectedDate,
            'clinic_id' => $clinic->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('daily-schedule/Index')
            ->where('scheduleData.day_of_week', $selectedDay)
            ->has('scheduleData.clinics', 1)
            ->where('scheduleData.clinics.0.id', $clinic->id)
            ->where('scheduleData.clinics.0.doctors.0.doctor_name', 'Clinic Doctor')
            ->where('scheduleData.clinics.0.doctors.0.start_time', '10:00')
            ->where('scheduleData.clinics.0.doctors.0.end_time', '14:00'));
    }
}
