<?php

namespace Tests\Feature\Doctors;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DoctorProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_clinic_doctor_profiles(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'department_id' => $department->id,
        ]);

        $otherDoctor = $this->createDoctorUser($otherClinic);
        DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('doctors.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $doctorProfile->id);
    }

    public function test_index_passes_clinic_working_hours_with_department_options(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Cardiology',
        ]);

        $this->setClinicWorkingHours($clinic, [
            'sunday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $response = $this->get(route('doctors.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('doctors/Index')
            ->where('departments.0.working_hours.1.day_of_week', 'sunday')
            ->where('departments.0.working_hours.1.is_active', true)
            ->where('departments.0.working_hours.1.start_time', '09:00')
            ->where('departments.0.working_hours.1.end_time', '17:00')
        );
    }

    public function test_store_creates_doctor_profile_with_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $department = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Cardiology',
            'code' => 'CARD',
        ]);

        $payload = [
            'name' => 'Dr New Account',
            'username' => 'doctor-new@example.com',
            'password' => 'password-123',
            'department_id' => $department->id,
            'gender' => DoctorProfile::GENDER_MALE,
            'phone' => '+963999000111',
            'license_number' => 'lic-2026-100',
            'specialty' => 'Cardiology',
            'consultation_duration_minutes' => 25,
            'status' => DoctorProfile::STATUS_ACTIVE,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'working_hours' => [
                ['day_of_week' => 6, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 0, 'is_active' => true, 'start_time' => '09:00', 'end_time' => '13:00'],
                ['day_of_week' => 1, 'is_active' => true, 'start_time' => '10:00', 'end_time' => '14:00'],
                ['day_of_week' => 2, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 3, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 4, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 5, 'is_active' => false, 'start_time' => null, 'end_time' => null],
            ],
            'bio' => 'Senior cardiology consultant.',
        ];

        $response = $this->postJson(route('doctors.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.department_id', $department->id);
        $response->assertJsonPath('data.license_number', 'LIC-2026-100');
        $response->assertJsonPath('data.specialty', 'Cardiology');

        $doctorProfileId = (int) $response->json('data.id');
        $doctorUserId = (int) $response->json('data.user_id');

        $this->assertDatabaseHas('doctor_profiles', [
            'id' => $doctorProfileId,
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUserId,
            'department_id' => $department->id,
            'gender' => DoctorProfile::GENDER_MALE,
            'phone' => '+963999000111',
            'license_number' => 'LIC-2026-100',
            'specialty' => 'Cardiology',
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $doctorUserId,
            'clinic_id' => $clinic->id,
            'email' => 'doctor-new@example.com',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('doctor_schedules', 2);
        $this->assertDatabaseHas('doctor_schedules', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctorUserId,
            'day_of_week' => 0,
            'is_available' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'doctor_profiles.create',
            'auditable_id' => $doctorProfileId,
        ]);
    }

    public function test_store_rejects_doctor_hours_outside_clinic_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);

        $this->setClinicWorkingHours($clinic, [
            'sunday' => ['start_time' => '09:00', 'end_time' => '17:00'],
            'thursday' => ['start_time' => '11:00', 'end_time' => '15:00'],
        ]);

        $response = $this->postJson(route('doctors.store'), [
            'name' => 'Dr Outside Hours',
            'username' => 'doctor-outside-hours@example.com',
            'password' => 'password-123',
            'department_id' => $department->id,
            'gender' => DoctorProfile::GENDER_MALE,
            'specialty' => 'Cardiology',
            'status' => DoctorProfile::STATUS_ACTIVE,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'working_hours' => [
                ['day_of_week' => 0, 'is_active' => true, 'start_time' => '08:00', 'end_time' => '18:00'],
                ['day_of_week' => 2, 'is_active' => true, 'start_time' => '10:00', 'end_time' => '12:00'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'working_hours.0.start_time',
            'working_hours.1.day_of_week',
        ]);
    }

    public function test_store_rejects_user_without_doctor_role(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $nonDoctorUser = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->postJson(route('doctors.store'), [
            'user_id' => $nonDoctorUser->id,
            'gender' => DoctorProfile::GENDER_MALE,
            'specialty' => 'Family Medicine',
            'consultation_duration_minutes' => 30,
            'status' => DoctorProfile::STATUS_ACTIVE,
            'compensation_type' => DoctorProfile::COMPENSATION_WEEKLY,
            'compensation_value' => 500,
            'working_hours' => [
                ['day_of_week' => 6, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 0, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 1, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 2, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 3, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 4, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 5, 'is_active' => false, 'start_time' => null, 'end_time' => null],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $department = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Search Department',
        ]);

        $matchingProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'department_id' => $department->id,
            'specialty' => 'Search Specialty',
            'license_number' => 'SEARCH-100',
        ]);

        $otherDoctor = $this->createDoctorUser($clinic);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $otherDoctor->id,
            'specialty' => 'General Practice',
            'license_number' => 'OTHER-200',
        ]);

        $response = $this->getJson(route('doctors.index', ['search' => 'Search Specialty']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingProfile->id);
    }

    public function test_index_applies_status_and_department_filters(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);
        $otherDepartment = Department::factory()->create(['clinic_id' => $clinic->id]);

        $activeProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'department_id' => $department->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $otherDoctor = $this->createDoctorUser($clinic);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $otherDoctor->id,
            'department_id' => $otherDepartment->id,
            'status' => DoctorProfile::STATUS_ON_LEAVE,
        ]);

        $response = $this->getJson(route('doctors.index', [
            'status' => DoctorProfile::STATUS_ACTIVE,
            'department_id' => $department->id,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $activeProfile->id);
    }

    public function test_index_applies_sorting_by_specialty(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $firstDoctor = $this->createDoctorUser($clinic);
        $secondDoctor = $this->createDoctorUser($clinic);

        $alphaProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $firstDoctor->id,
            'specialty' => 'Alpha Specialty',
        ]);

        $zuluProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $secondDoctor->id,
            'specialty' => 'Zulu Specialty',
        ]);

        $ascResponse = $this->getJson(route('doctors.index', [
            'sort_by' => 'specialty',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $alphaProfile->id);
        $ascResponse->assertJsonPath('data.1.id', $zuluProfile->id);

        $descResponse = $this->getJson(route('doctors.index', [
            'sort_by' => 'specialty',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $zuluProfile->id);
        $descResponse->assertJsonPath('data.1.id', $alphaProfile->id);
    }

    public function test_doctor_index_returns_only_own_profile(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');
        $otherDoctor = $this->createDoctorUser($clinic);

        $assignedProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('doctors.index'));

        $response->assertForbidden();
    }

    public function test_show_returns_404_for_profile_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $otherDoctor = $this->createDoctorUser($otherClinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('doctors.show', ['doctorProfileId' => $profile->id]));

        $response->assertNotFound();
    }

    public function test_update_updates_doctor_profile_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $replacementDoctor = $this->createDoctorUser($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);

        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'specialty' => 'Old Specialty',
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'user_id' => $replacementDoctor->id,
            'name' => 'Updated Doctor Name',
            'username' => 'updated-doctor@example.com',
            'password' => '',
            'department_id' => $department->id,
            'gender' => DoctorProfile::GENDER_FEMALE,
            'phone' => '+963944222333',
            'specialty' => 'Updated Specialty',
            'consultation_duration_minutes' => 40,
            'status' => DoctorProfile::STATUS_ON_LEAVE,
            'license_number' => 'upd-777',
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY,
            'compensation_value' => 2500,
            'working_hours' => [
                ['day_of_week' => 6, 'is_active' => true, 'start_time' => '09:00', 'end_time' => '17:00'],
                ['day_of_week' => 0, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 1, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 2, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 3, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 4, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 5, 'is_active' => false, 'start_time' => null, 'end_time' => null],
            ],
            'bio' => 'Updated bio',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user_id', $replacementDoctor->id);
        $response->assertJsonPath('data.department_id', $department->id);
        $response->assertJsonPath('data.specialty', 'Updated Specialty');
        $response->assertJsonPath('data.status', DoctorProfile::STATUS_ON_LEAVE);
        $response->assertJsonPath('data.license_number', 'UPD-777');

        $this->assertDatabaseHas('doctor_profiles', [
            'id' => $profile->id,
            'clinic_id' => $clinic->id,
            'user_id' => $replacementDoctor->id,
            'department_id' => $department->id,
            'gender' => DoctorProfile::GENDER_FEMALE,
            'phone' => '+963944222333',
            'specialty' => 'Updated Specialty',
            'status' => DoctorProfile::STATUS_ON_LEAVE,
            'license_number' => 'UPD-777',
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $replacementDoctor->id,
            'name' => 'Updated Doctor Name',
            'email' => 'updated-doctor@example.com',
        ]);

        $this->assertDatabaseHas('doctor_schedules', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $replacementDoctor->id,
            'day_of_week' => 6,
            'is_available' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'doctor_profiles.update',
            'auditable_id' => $profile->id,
        ]);
    }

    public function test_update_rejects_doctor_hours_outside_clinic_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'department_id' => $department->id,
        ]);

        $this->setClinicWorkingHours($clinic, [
            'sunday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'department_id' => $department->id,
            'working_hours' => [
                ['day_of_week' => 0, 'is_active' => true, 'start_time' => '07:00', 'end_time' => '13:00'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['working_hours.0.start_time']);
    }

    public function test_destroy_deletes_doctor_profile_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $response = $this->deleteJson(route('doctors.destroy', ['doctorProfileId' => $profile->id]));

        $response->assertNoContent();
        $this->assertSoftDeleted($profile);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'doctor_profiles.delete',
            'auditable_id' => $profile->id,
        ]);
    }

    public function test_destroy_archives_doctor_with_visit_history(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $response = $this->deleteJson(route('doctors.destroy', ['doctorProfileId' => $profile->id]));

        $response->assertNoContent();
        $this->assertNotSoftDeleted($profile);
        $this->assertDatabaseHas('doctor_profiles', [
            'id' => $profile->id,
            'status' => DoctorProfile::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $doctor->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'doctor_profiles.archive',
            'auditable_id' => $profile->id,
        ]);
    }

    public function test_bulk_destroy_deletes_only_profiles_within_clinic_scope(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $deletableProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $otherDoctor = $this->createDoctorUser($otherClinic);
        $otherClinicProfile = DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctor->id,
        ]);

        $response = $this->deleteJson(route('doctors.bulk-destroy'), [
            'ids' => [$deletableProfile->id, $otherClinicProfile->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($deletableProfile);
        $this->assertDatabaseHas('doctor_profiles', ['id' => $otherClinicProfile->id]);
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

    private function createDoctorUser(Clinic $clinic): User
    {
        $doctor = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($doctor, 'doctor');

        return $doctor;
    }

    /**
     * @param  array<string, array{start_time: string, end_time: string}>  $activeDays
     */
    private function setClinicWorkingHours(Clinic $clinic, array $activeDays): void
    {
        foreach (ClinicWorkingHour::DAYS as $day) {
            $hours = $activeDays[$day] ?? null;

            ClinicWorkingHour::query()->create([
                'clinic_id' => $clinic->id,
                'day_of_week' => $day,
                'is_active' => $hours !== null,
                'start_time' => $hours['start_time'] ?? null,
                'end_time' => $hours['end_time'] ?? null,
            ]);
        }
    }
}
