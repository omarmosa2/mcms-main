<?php

namespace Tests\Feature\Doctors;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_200_for_doctor_in_other_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinicA);

        // Doctor in clinicB
        $doctor = DoctorProfile::create([
            'clinic_id' => $clinicB->id,
            'full_name' => 'Other Clinic Doctor',
            'specialty' => 'Test',
            'compensation_type' => 'percentage',
            'is_active' => true,
        ]);

        DoctorSchedule::create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinicB->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->getJson(route('doctors.show', $doctor->id));

        $response->assertOk();
        $response->assertJsonPath('data.id', $doctor->id);
        $response->assertJsonPath('data.full_name', 'Other Clinic Doctor');
        $this->assertSame(1, count($response->json('data.schedules')));
    }

    public function test_update_replaces_schedules_for_doctor_in_other_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $this->authenticateForClinic($clinicA);

        $this->seedClinicWorkingHours($clinicB, [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $doctor = DoctorProfile::create([
            'clinic_id' => $clinicB->id,
            'full_name' => 'Cross Clinic Doctor',
            'specialty' => 'Test',
            'compensation_type' => 'percentage',
            'compensation_value' => 20,
            'is_active' => true,
        ]);

        DoctorSchedule::create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinicB->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '15:00',
            'is_available' => true,
        ]);

        $payload = [
            'full_name' => 'Cross Clinic Updated',
            'specialty' => 'Neuro',
            'compensation_type' => 'weekly_fixed',
            'compensation_value' => '500',
            'schedules' => [
                ['day_of_week' => 2, 'is_available' => true, 'start_time' => '10:00', 'end_time' => '13:00'],
            ],
        ];

        $response = $this->put(route('doctors.update', $doctor->id), $payload);

        $response->assertRedirect(route('doctors.index'));

        $doctor->refresh();
        $this->assertSame('Cross Clinic Updated', $doctor->full_name);
        $this->assertSame(
            1,
            DoctorSchedule::query()->withoutGlobalScope('clinic')->where('doctor_profile_id', $doctor->id)->count(),
        );
        $this->assertDatabaseHas('doctor_schedules', [
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => 2,
        ]);
        $this->assertDatabaseMissing('doctor_schedules', [
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => 1,
        ]);
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'clinic_admin'): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($user, $roleName);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string}>  $days
     */
    private function seedClinicWorkingHours(Clinic $clinic, array $days): void
    {
        foreach ($days as $day) {
            ClinicWorkingHour::create([
                'clinic_id' => $clinic->id,
                'day_of_week' => $day['day_of_week'],
                'is_active' => true,
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
            ]);
        }
    }
}
