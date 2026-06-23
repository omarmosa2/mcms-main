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

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_doctors_page(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Test Index',
        ]);

        $response = $this->get(route('doctors.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('doctors.data', 1)
            ->has('clinics')
            ->where('doctors.data.0.full_name', 'Dr. Test Index'));
    }

    public function test_index_excludes_administrative_clinics_from_clinic_options(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        Clinic::factory()->create([
            'code' => 'ADMIN001',
            'name' => 'Administration Clinic',
            'is_administrative' => true,
        ]);

        $response = $this->get(route('doctors.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('clinics', 1)
            ->where('clinics.0.id', $clinic->id));
    }

    public function test_store_creates_doctor_with_schedules(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $this->seedClinicWorkingHours($clinic, [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $payload = [
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Store Test',
            'gender' => 'male',
            'specialty' => 'Cardiology',
            'phone' => '1234567890',
            'email' => 'doctor@example.com',
            'username' => 'drstore',
            'employment_start_date' => '2026-01-01',
            'compensation_type' => 'percentage',
            'compensation_value' => '30',
            'is_active' => true,
            'notes' => 'Test note',
            'schedules' => [
                [
                    'day_of_week' => 0,
                    'is_available' => true,
                    'start_time' => '10:00',
                    'end_time' => '16:00',
                ],
                [
                    'day_of_week' => 1,
                    'is_available' => true,
                    'start_time' => '10:00',
                    'end_time' => '15:00',
                ],
            ],
        ];

        $response = $this->post(route('doctors.store'), $payload);

        $response->assertRedirect(route('doctors.index'));

        $this->assertDatabaseHas('doctor_profiles', [
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Store Test',
            'specialty' => 'Cardiology',
            'compensation_type' => 'percentage',
            'is_active' => true,
        ]);

        $doctor = DoctorProfile::query()
            ->where('clinic_id', $clinic->id)
            ->where('full_name', 'Dr. Store Test')
            ->firstOrFail();

        $this->assertSame(2, $doctor->schedules()->count());
        $this->assertDatabaseHas('doctor_schedules', [
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 0,
            'is_available' => true,
        ]);
    }

    public function test_store_requires_at_least_one_active_schedule(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $this->seedClinicWorkingHours($clinic, [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $payload = [
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. No Schedule',
            'gender' => 'male',
            'specialty' => 'General',
            'compensation_type' => 'percentage',
            'compensation_value' => '20',
            'schedules' => [
                ['day_of_week' => 0, 'is_available' => false, 'start_time' => null, 'end_time' => null],
            ],
        ];

        $response = $this->post(route('doctors.store'), $payload);

        $response->assertSessionHasErrors('schedules');
        $this->assertDatabaseMissing('doctor_profiles', [
            'full_name' => 'Dr. No Schedule',
        ]);
    }

    public function test_store_rejects_schedule_outside_clinic_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $this->seedClinicWorkingHours($clinic, [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $payload = [
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Out Of Bounds',
            'gender' => 'male',
            'specialty' => 'General',
            'compensation_type' => 'percentage',
            'compensation_value' => '20',
            'schedules' => [
                ['day_of_week' => 0, 'is_available' => true, 'start_time' => '08:00', 'end_time' => '18:00'],
            ],
        ];

        $response = $this->post(route('doctors.store'), $payload);

        $response->assertSessionHasErrors(['schedules.0.start_time']);
    }

    public function test_show_returns_doctor_with_schedules(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Show Test',
        ]);

        DoctorSchedule::create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->getJson(route('doctors.show', $doctor->id));

        $response->assertOk();
        $response->assertJsonPath('data.full_name', 'Dr. Show Test');
        $response->assertJsonPath('data.schedules.0.day_of_week', 2);
        $response->assertJsonPath('data.schedules.0.start_time', '10:00');
        $response->assertJsonPath('data.schedules.0.end_time', '14:00');
    }

    public function test_update_replaces_schedules(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $this->seedClinicWorkingHours($clinic, [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Dr. Update Test',
        ]);

        DoctorSchedule::create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '15:00',
            'is_available' => true,
        ]);

        $payload = [
            'full_name' => 'Dr. Updated Name',
            'specialty' => 'Neurology',
            'compensation_type' => 'weekly_fixed',
            'compensation_value' => '1000',
            'schedules' => [
                ['day_of_week' => 1, 'is_available' => true, 'start_time' => '10:00', 'end_time' => '13:00'],
                ['day_of_week' => 2, 'is_available' => true, 'start_time' => '11:00', 'end_time' => '16:00'],
            ],
        ];

        $response = $this->put(route('doctors.update', $doctor->id), $payload);

        $response->assertRedirect(route('doctors.index'));

        $doctor->refresh();

        $this->assertSame('Dr. Updated Name', $doctor->full_name);
        $this->assertSame('Neurology', $doctor->specialty);
        $this->assertSame(2, $doctor->schedules()->count());
        $this->assertDatabaseMissing('doctor_schedules', [
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => 0,
        ]);
        $this->assertDatabaseHas('doctor_schedules', [
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_destroy_deletes_doctor_and_schedules_only(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $otherDoctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        DoctorSchedule::create([
            'doctor_profile_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '15:00',
            'is_available' => true,
        ]);

        $response = $this->delete(route('doctors.destroy', $doctor->id));

        $response->assertRedirect(route('doctors.index'));

        $this->assertDatabaseMissing('doctor_profiles', ['id' => $doctor->id]);
        $this->assertDatabaseMissing('doctor_schedules', ['doctor_profile_id' => $doctor->id]);
        $this->assertDatabaseHas('doctor_profiles', ['id' => $otherDoctor->id]);
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }

    public function test_doctor_role_cannot_access_doctors_index(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'doctor');

        $response = $this->get(route('doctors.index'));

        $response->assertForbidden();
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Alice Searchable',
        ]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Bob Hidden',
        ]);

        $response = $this->get(route('doctors.index', ['search' => 'Alice']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('doctors.data', 1)
            ->where('doctors.data.0.full_name', 'Alice Searchable'));
    }

    public function test_index_applies_clinic_filter(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $this->authenticateForClinic($clinicA);

        $doctorA = DoctorProfile::factory()->create([
            'clinic_id' => $clinicA->id,
            'full_name' => 'Clinic A Doctor',
        ]);
        $doctorB = DoctorProfile::factory()->create([
            'clinic_id' => $clinicB->id,
            'full_name' => 'Clinic B Doctor',
        ]);

        $response = $this->get(route('doctors.index', ['clinic_id' => $clinicA->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('doctors.data', 1)
            ->where('doctors.data.0.full_name', 'Clinic A Doctor'));
    }

    public function test_index_applies_is_active_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Active Doctor',
            'is_active' => true,
        ]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Inactive Doctor',
            'is_active' => false,
        ]);

        $response = $this->get(route('doctors.index', ['is_active' => '0']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('doctors.data', 1)
            ->where('doctors.data.0.full_name', 'Inactive Doctor'));
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'clinic_admin'): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

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
