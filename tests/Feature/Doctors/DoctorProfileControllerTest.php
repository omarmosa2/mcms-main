<?php

namespace Tests\Feature\Doctors;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DoctorProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_doctor_profiles_for_admin(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $clinic3 = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $otherDoctor = $this->createDoctorUser($otherClinic);
        $otherDoctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('doctors.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_returns_only_own_doctor_for_doctor_role(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'doctor');

        $doctor = $this->createDoctorUser($clinic);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $otherDoctor = $this->createDoctorUser($otherClinic);
        DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('doctors.index'));

        $response->assertForbidden();
    }

    public function test_index_passes_clinic_options_without_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->get(route('doctors.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('doctors/Index')
            ->has('doctor_profiles')
            ->has('doctors')
            ->has('clinics')
        );
    }

    public function test_index_passes_doctor_stats_for_all_clinics_to_an_administrator(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $this->createDoctorUser($clinic)->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $this->createDoctorUser($clinic)->id,
            'status' => DoctorProfile::STATUS_ON_LEAVE,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $this->createDoctorUser($clinic)->id,
            'status' => DoctorProfile::STATUS_INACTIVE,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $this->createDoctorUser($clinic)->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ])->delete();

        DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $this->createDoctorUser($otherClinic)->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $response = $this->get(route('doctors.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('doctors/Index')
            ->where('stats.total_doctors', 4)
            ->where('stats.active_doctors', 2)
            ->where('stats.on_leave_doctors', 1)
            ->where('stats.inactive_doctors', 1)
        );
    }

    public function test_store_creates_doctor_profile_with_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);

        $payload = [
            'clinic_id' => $clinic->id,
            'name' => 'Dr New Account',
            'username' => 'doctor-new@example.com',
            'password' => 'password-123',
            'gender' => DoctorProfile::GENDER_MALE,
            'phone' => '+963999000111',
            'work_start_date' => '2026-06-14',
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
        $response->assertJsonPath('data.license_number', 'LIC-2026-100');
        $response->assertJsonPath('data.work_start_date', '2026-06-14');
        $response->assertJsonPath('data.specialty', 'Cardiology');

        $doctorProfileId = (int) $response->json('data.id');
        $doctorUserId = (int) $response->json('data.user_id');

        $this->assertDatabaseHas('doctor_profiles', [
            'id' => $doctorProfileId,
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUserId,
            'gender' => DoctorProfile::GENDER_MALE,
            'phone' => '+963999000111',
            'license_number' => 'LIC-2026-100',
            'specialty' => 'Cardiology',
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
        ]);
        $this->assertSame('2026-06-14', DoctorProfile::findOrFail($doctorProfileId)->work_start_date?->toDateString());

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

        $this->setClinicWorkingHours($clinic, [
            'sunday' => ['start_time' => '09:00', 'end_time' => '17:00'],
            'thursday' => ['start_time' => '11:00', 'end_time' => '15:00'],
        ]);

        $response = $this->postJson(route('doctors.store'), [
            'clinic_id' => $clinic->id,
            'name' => 'Dr Outside Hours',
            'username' => 'doctor-outside-hours@example.com',
            'password' => 'password-123',
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
            'clinic_id' => $clinic->id,
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

        $matchingProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
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

        $activeProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $otherDoctor = $this->createDoctorUser($clinic);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $otherDoctor->id,
            'status' => DoctorProfile::STATUS_ON_LEAVE,
        ]);

        $response = $this->getJson(route('doctors.index', [
            'status' => DoctorProfile::STATUS_ACTIVE,
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

    public function test_show_returns_profile_for_admin_across_clinics(): void
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

        $response->assertOk();
        $response->assertJsonPath('data.id', $profile->id);
    }

    public function test_show_returns_clinic_days_and_doctor_schedules_using_numeric_weekdays(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => 3,
            'is_active' => true,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);
        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => 4,
            'is_active' => false,
            'start_time' => null,
            'end_time' => null,
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 3,
            'is_available' => true,
            'start_time' => '11:00',
            'end_time' => '17:00',
        ]);

        $indexResponse = $this->getJson(route('doctors.index'));

        $indexResponse->assertOk();
        $indexResponse->assertJsonPath('data.0.clinic_working_days.0.day_of_week', 3);
        $indexResponse->assertJsonPath('data.0.doctor_schedules.0.start_time', '11:00');

        $response = $this->getJson(route('doctors.show', ['doctorProfileId' => $profile->id]));

        $response->assertOk();
        $response->assertJsonPath('data.clinic_working_days.0.day_of_week', 3);
        $response->assertJsonPath('data.clinic_working_days.0.start_time', '09:00');
        $response->assertJsonPath('data.doctor_schedules.0.day_of_week', 3);
        $response->assertJsonPath('data.doctor_schedules.0.start_time', '11:00');
        $response->assertJsonMissingPath('data.working_hours');
    }

    public function test_update_updates_doctor_profile_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $replacementDoctor = $this->createDoctorUser($clinic);

        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'specialty' => 'Old Specialty',
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $clinic->id,
            'user_id' => $replacementDoctor->id,
            'name' => 'Updated Doctor Name',
            'username' => 'updated-doctor@example.com',
            'password' => '',
            'gender' => DoctorProfile::GENDER_FEMALE,
            'phone' => '+963944222333',
            'work_start_date' => '2026-07-01',
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
        $response->assertJsonPath('data.specialty', 'Updated Specialty');
        $response->assertJsonPath('data.status', DoctorProfile::STATUS_ON_LEAVE);
        $response->assertJsonPath('data.work_start_date', '2026-07-01');
        $response->assertJsonPath('data.license_number', 'UPD-777');

        $this->assertDatabaseHas('doctor_profiles', [
            'id' => $profile->id,
            'clinic_id' => $clinic->id,
            'user_id' => $replacementDoctor->id,
            'gender' => DoctorProfile::GENDER_FEMALE,
            'phone' => '+963944222333',
            'specialty' => 'Updated Specialty',
            'status' => DoctorProfile::STATUS_ON_LEAVE,
            'license_number' => 'UPD-777',
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY,
        ]);
        $this->assertSame('2026-07-01', DoctorProfile::findOrFail($profile->id)->work_start_date?->toDateString());

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
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $this->setClinicWorkingHours($clinic, [
            'sunday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $clinic->id,
            'working_hours' => [
                ['day_of_week' => 0, 'is_active' => true, 'start_time' => '07:00', 'end_time' => '13:00'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['working_hours.0.start_time']);
    }

    public function test_update_replaces_doctor_schedules_with_only_active_days(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
            'is_available' => true,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 3,
            'is_available' => true,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $clinic->id,
            'working_hours' => [
                ['day_of_week' => 2, 'is_active' => false, 'start_time' => null, 'end_time' => null],
                ['day_of_week' => 3, 'is_active' => true, 'start_time' => '11:00', 'end_time' => '17:00'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('doctor_schedules', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
        ]);
        $this->assertDatabaseHas('doctor_schedules', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 3,
            'start_time' => '11:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);
        $this->assertSame(1, DoctorSchedule::query()->count());
    }

    public function test_update_allows_the_doctors_current_email_but_rejects_another_doctors_email(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $doctor->forceFill(['email' => 'lena@example.com'])->save();
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $otherDoctor = $this->createDoctorUser($clinic);
        $otherDoctor->forceFill(['email' => 'other-doctor@example.com'])->save();

        $sameEmailResponse = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $clinic->id,
            'username' => 'lena@example.com',
        ]);

        $sameEmailResponse->assertOk();
        $sameEmailResponse->assertJsonMissingValidationErrors('username');
        $this->assertDatabaseHas('users', [
            'id' => $doctor->id,
            'email' => 'lena@example.com',
        ]);

        $duplicateEmailResponse = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $clinic->id,
            'username' => 'other-doctor@example.com',
        ]);

        $duplicateEmailResponse->assertUnprocessable();
        $duplicateEmailResponse->assertJsonValidationErrors('username');
    }

    public function test_update_uses_the_doctor_profiles_clinic_when_updating_its_account(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $clinic->id,
            'name' => 'Updated Doctor',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'name' => 'Updated Doctor',
        ]);
    }

    public function test_update_requires_a_clinic_id(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'name' => 'Updated Doctor',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('clinic_id');
    }

    public function test_update_moves_the_doctor_and_replaces_schedules_for_the_selected_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $targetClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'is_available' => true,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $targetClinic->id,
            'working_hours' => [
                ['day_of_week' => 2, 'is_active' => true, 'start_time' => '10:00', 'end_time' => '15:00'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('doctor_profiles', ['id' => $profile->id, 'clinic_id' => $targetClinic->id]);
        $this->assertDatabaseHas('users', ['id' => $doctor->id, 'clinic_id' => $targetClinic->id]);
        $this->assertDatabaseMissing('doctor_schedules', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
        ]);
        $this->assertDatabaseHas('doctor_schedules', [
            'clinic_id' => $targetClinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
        ]);
    }

    public function test_update_does_not_move_a_doctor_with_appointment_history_to_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $targetClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = $this->createDoctorUser($clinic);
        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);
        Appointment::factory()->create(['clinic_id' => $clinic->id, 'doctor_id' => $doctor->id]);

        $response = $this->putJson(route('doctors.update', ['doctorProfileId' => $profile->id]), [
            'clinic_id' => $targetClinic->id,
            'working_hours' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('clinic_id');
        $this->assertDatabaseHas('doctor_profiles', ['id' => $profile->id, 'clinic_id' => $clinic->id]);
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
        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
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

    public function test_bulk_destroy_deletes_selected_profiles_for_an_administrator(): void
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
        $response->assertJsonPath('data.deleted_count', 2);
        $response->assertJsonPath('data.failed_count', 0);

        $this->assertSoftDeleted($deletableProfile);
        $this->assertSoftDeleted($otherClinicProfile);
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
     * @param  array<int, array{start_time: string, end_time: string}>  $activeDays
     */
    private function setClinicWorkingHours(Clinic $clinic, array $activeDays): void
    {
        $nameToIndex = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2,
            'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6,
        ];

        $normalized = [];
        foreach ($activeDays as $day => $hours) {
            $index = is_int($day) ? $day : ($nameToIndex[$day] ?? $day);
            $normalized[$index] = $hours;
        }

        foreach (ClinicWorkingHour::DAYS as $dayIndex) {
            $hours = $normalized[$dayIndex] ?? null;

            ClinicWorkingHour::query()->create([
                'clinic_id' => $clinic->id,
                'day_of_week' => $dayIndex,
                'is_active' => $hours !== null,
                'start_time' => $hours['start_time'] ?? null,
                'end_time' => $hours['end_time'] ?? null,
            ]);
        }
    }
}
