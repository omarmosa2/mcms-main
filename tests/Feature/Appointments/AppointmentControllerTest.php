<?php

namespace Tests\Feature\Appointments;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\Department;
use App\Models\DoctorLeave;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_doctor_own_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-1000',
        ]);

        Appointment::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-2000',
        ]);

        $response = $this->getJson(route('appointments.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $appointment->id);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'action' => 'appointments.index',
        ]);
    }

    public function test_clinic_admin_can_view_appointments_from_all_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-1000',
        ]);

        $otherAppointment = Appointment::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'appointment_number' => 'APT-2000',
        ]);

        $response = $this->getJson(route('appointments.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_receptionist_can_view_appointments_from_all_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-1000',
        ]);

        Appointment::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'appointment_number' => 'APT-2000',
        ]);

        $response = $this->getJson(route('appointments.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_selected_clinic_filter_populates_table_and_today_appointments(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        try {
            $userClinic = Clinic::factory()->create(['name' => 'User Clinic']);
            $selectedClinic = Clinic::factory()->create(['name' => 'Selected Clinic']);
            $this->authenticateForClinic($userClinic);

            $patient = Patient::factory()->create([
                'clinic_id' => $selectedClinic->id,
                'first_name' => 'Omar',
                'last_name' => 'Saleh',
            ]);

            $appointment = Appointment::factory()->create([
                'clinic_id' => $selectedClinic->id,
                'patient_id' => $patient->id,
                'scheduled_for' => Carbon::parse('2026-06-28 12:00:00'),
                'status' => Appointment::STATUS_SCHEDULED,
            ]);

            Appointment::factory()->create([
                'clinic_id' => $userClinic->id,
                'patient_id' => Patient::factory()->create(['clinic_id' => $userClinic->id])->id,
                'scheduled_for' => Carbon::parse('2026-06-28 11:00:00'),
                'status' => Appointment::STATUS_SCHEDULED,
            ]);

            $response = $this->get(route('appointments.index', [
                'clinic_id' => $selectedClinic->id,
            ]));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->where('appointments.data.0.id', $appointment->id)
                ->where('appointments.data.0.clinic.id', $selectedClinic->id)
                ->where('appointments.data.0.clinic.name', 'Selected Clinic')
                ->where('appointments.data.0.patient.full_name', 'Omar Saleh')
                ->where('today_appointments.0.id', $appointment->id)
                ->where('today_appointments.0.clinic.id', $selectedClinic->id)
                ->where('today_appointments.0.patient.full_name', 'Omar Saleh'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_index_without_clinic_filter_populates_table_and_today_appointments_from_all_clinics(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        try {
            $adminClinic = Clinic::factory()->create(['name' => 'Admin Clinic']);
            $otherClinic = Clinic::factory()->create(['name' => 'Dermatology Clinic']);
            $this->authenticateForClinic($adminClinic, 'admin');

            $patient = Patient::factory()->create([
                'clinic_id' => $otherClinic->id,
                'first_name' => 'Omar',
                'last_name' => 'Saleh',
            ]);

            $appointment = Appointment::factory()->create([
                'clinic_id' => $otherClinic->id,
                'patient_id' => $patient->id,
                'scheduled_for' => Carbon::parse('2026-06-28 12:00:00'),
                'status' => Appointment::STATUS_SCHEDULED,
            ]);

            $response = $this->get(route('appointments.index'));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->where('appointments.data.0.id', $appointment->id)
                ->where('appointments.data.0.clinic.id', $otherClinic->id)
                ->where('appointments.data.0.patient.full_name', 'Omar Saleh')
                ->where('today_appointments.0.id', $appointment->id)
                ->where('today_appointments.0.clinic.id', $otherClinic->id)
                ->where('today_appointments.0.patient.full_name', 'Omar Saleh'));
        } finally {
            Carbon::setTestNow();
        }
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

        $response = $this->get(route('appointments.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('clinics', 1)
            ->where('clinics.0.id', $clinic->id));
    }

    public function test_index_passes_only_today_available_clinics_and_doctors(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create();
        $closedClinic = Clinic::factory()->create(['name' => 'Closed Clinic']);
        $admin = $this->authenticateForClinic($clinic);
        $unavailableDoctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $availableDoctor = User::factory()->create(['clinic_id' => $clinic->id]);

        app(AssignUserRoleAction::class)->handle($unavailableDoctor, 'doctor', $admin->id);
        app(AssignUserRoleAction::class)->handle($availableDoctor, 'doctor', $admin->id);

        $unavailableProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $unavailableDoctor->id,
            'is_active' => true,
        ]);
        ClinicWorkingHour::query()->create([
            'clinic_id' => $closedClinic->id,
            'day_of_week' => Carbon::TUESDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        $availableProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $availableDoctor->id,
            'is_active' => true,
        ]);

        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        foreach ([$unavailableProfile, $availableProfile] as $profile) {
            DoctorSchedule::query()->create([
                'clinic_id' => $clinic->id,
                'doctor_profile_id' => $profile->id,
                'day_of_week' => Carbon::MONDAY,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_available' => true,
            ]);
        }

        DoctorLeave::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $unavailableDoctor->id,

            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => '2026-06-15',
        ]);

        $response = $this->get(route('appointments.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('appointments/Index')
            ->has('today_availability.clinics', 1)
            ->where('today_availability.clinics.0', $clinic->id)
            ->has('today_availability.clinic_options', 1)
            ->where('today_availability.clinic_options.0.id', $clinic->id)
            ->has('today_availability.doctors', 1)
            ->where('today_availability.doctors.0.id', $availableDoctor->id)
            ->where('today_availability.doctors.0.clinic_id', $clinic->id)
            ->where('today_availability.doctors.0.available_periods.0.start_time', '09:00')
        );

        Carbon::setTestNow();
    }

    public function test_booking_options_are_loaded_from_current_day_database_schedules(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create(['name' => 'Available Clinic']);
        $closedClinic = Clinic::factory()->create(['name' => 'Closed Clinic']);
        $admin = $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Available Doctor']);
        app(AssignUserRoleAction::class)->handle($doctorUser, 'doctor', $admin->id);

        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);

        ClinicWorkingHour::query()->create([
            'clinic_id' => $clinic->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        ClinicWorkingHour::query()->create([
            'clinic_id' => $closedClinic->id,
            'day_of_week' => Carbon::TUESDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->getJson(route('appointments.booking-options'));

        $response->assertOk();
        $response->assertJsonPath('data.date', '2026-06-15');
        $response->assertJsonPath('data.clinic_options.0.id', $clinic->id);
        $response->assertJsonMissingPath('data.clinic_options.1');
        $response->assertJsonPath('data.doctors.0.id', $doctorUser->id);
        $response->assertJsonPath('data.doctors.0.clinic_id', $clinic->id);
        $response->assertJsonPath('data.doctors.0.available_periods.0.start_time', '10:00');
        $response->assertJsonPath('data.doctors.0.available_periods.0.end_time', '14:00');

        Carbon::setTestNow();
    }

    public function test_booking_options_keep_all_today_clinics_when_filtering_doctors_by_selected_clinic(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create(['name' => 'First Clinic']);
        $otherClinic = Clinic::factory()->create(['name' => 'Second Clinic']);
        $this->authenticateForClinic($clinic);

        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'First Clinic Doctor']);
        $otherDoctorUser = User::factory()->create(['clinic_id' => $otherClinic->id, 'name' => 'Second Clinic Doctor']);

        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);
        $otherDoctor = DoctorProfile::factory()->create([
            'clinic_id' => $otherClinic->id,
            'user_id' => $otherDoctorUser->id,
            'is_active' => true,
        ]);

        foreach ([$clinic, $otherClinic] as $openClinic) {
            ClinicWorkingHour::query()->create([
                'clinic_id' => $openClinic->id,
                'day_of_week' => Carbon::MONDAY,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]);
        }

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctor->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'is_available' => true,
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $otherClinic->id,
            'doctor_profile_id' => $otherDoctor->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '11:00',
            'end_time' => '15:00',
            'is_available' => true,
        ]);

        $response = $this->getJson(route('appointments.booking-options', [
            'clinic_id' => $clinic->id,
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data.clinic_options');
        $response->assertJsonPath('data.clinic_options.0.id', $clinic->id);
        $response->assertJsonPath('data.clinic_options.1.id', $otherClinic->id);
        $response->assertJsonCount(1, 'data.doctors');
        $response->assertJsonPath('data.doctors.0.id', $doctorUser->id);
        $response->assertJsonPath('data.doctors.0.name', 'First Clinic Doctor');
        $response->assertJsonPath('data.doctors.0.available_periods.0.start_time', '10:00');

        Carbon::setTestNow();
    }

    public function test_store_creates_scheduled_appointment_with_audit_log(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);
        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);
        $this->setClinicWorkingHours($clinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-3000',
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 45,
            'appointment_type' => 'first_visit',
            'cost' => 500,
            'notes' => 'First consultation',
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.appointment_number', 'APT-3000');
        $response->assertJsonPath('data.status', Appointment::STATUS_SCHEDULED);

        $appointment = Appointment::query()->where('appointment_number', 'APT-3000')->firstOrFail();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'created_by' => $user->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'appointments.create',
            'auditable_id' => $appointment->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_store_generates_next_appointment_number_for_selected_clinic(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        try {
            $userClinic = Clinic::factory()->create();
            $selectedClinic = Clinic::factory()->create();
            $admin = $this->authenticateForClinic($userClinic);
            $patient = Patient::factory()->create(['clinic_id' => $userClinic->id]);
            [$doctor, $doctorProfile] = $this->createActiveDoctorForClinic($selectedClinic, $admin);

            $this->setClinicWorkingHours($selectedClinic, [
                Carbon::SUNDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
            ]);

            DoctorSchedule::query()->create([
                'clinic_id' => $selectedClinic->id,
                'doctor_profile_id' => $doctorProfile->id,
                'day_of_week' => Carbon::SUNDAY,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_available' => true,
            ]);

            Appointment::factory()->create([
                'clinic_id' => $selectedClinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_number' => 'APT-20260628-0001',
                'scheduled_for' => '2026-06-28 10:00:00',
                'duration_minutes' => 30,
                'status' => Appointment::STATUS_SCHEDULED,
            ]);

            $response = $this->postJson(route('appointments.store'), [
                'clinic_id' => $selectedClinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_for' => '2026-06-28T12:30:00',
                'duration_minutes' => 30,
                'appointment_type' => 'first_visit',
                'cost' => 250,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.appointment_number', 'APT-20260628-0002');

            $this->assertDatabaseHas('appointments', [
                'clinic_id' => $selectedClinic->id,
                'appointment_number' => 'APT-20260628-0002',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_store_rejects_patient_label_instead_of_patient_id(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('appointments.store'), [
            'patient_id' => 'مريض تجريبي 1 - 2',
            'doctor_id' => null,
            'appointment_number' => 'APT-PATIENT-LABEL',
            'scheduled_for' => now()->addHours(3)->seconds(0)->millisecond(0)->toISOString(),
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['patient_id']);

        $this->assertDatabaseMissing('appointments', [
            'appointment_number' => 'APT-PATIENT-LABEL',
        ]);
    }

    public function test_store_allows_future_appointment_inside_clinic_and_doctor_hours(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        [$doctor, $profile] = $this->createActiveDoctorForClinic($clinic, $admin);

        $this->setClinicWorkingHours($clinic, [
            Carbon::TUESDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $profile->id,
            'day_of_week' => Carbon::TUESDAY,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-FUTURE-001',
            'scheduled_for' => '2026-06-16T10:30:00+00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.doctor_id', $doctor->id);

        Carbon::setTestNow();
    }

    public function test_store_rejects_past_time_on_current_day(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        [$doctor, $profile] = $this->createActiveDoctorForClinic($clinic, $admin);

        $this->setClinicWorkingHours($clinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $profile->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $response = $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15T09:30:00+00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_for']);

        Carbon::setTestNow();
    }

    public function test_store_rejects_overlapping_doctor_or_patient_appointment(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        [$doctor, $profile] = $this->createActiveDoctorForClinic($clinic, $admin);
        [$otherDoctor, $otherProfile] = $this->createActiveDoctorForClinic($clinic, $admin);

        $this->setClinicWorkingHours($clinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        foreach ([$profile, $otherProfile] as $doctorProfile) {
            DoctorSchedule::query()->create([
                'clinic_id' => $clinic->id,
                'doctor_profile_id' => $doctorProfile->id,
                'day_of_week' => Carbon::MONDAY,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_available' => true,
            ]);
        }

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15 10:00:00',
            'duration_minutes' => 60,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $doctorConflict = $this->postJson(route('appointments.store'), [
            'patient_id' => $otherPatient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15T10:30:00+00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $doctorConflict->assertUnprocessable();
        $doctorConflict->assertJsonValidationErrors(['scheduled_for']);

        $patientConflict = $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
            'scheduled_for' => '2026-06-15T10:30:00+00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $patientConflict->assertUnprocessable();
        $patientConflict->assertJsonValidationErrors(['scheduled_for']);

        Carbon::setTestNow();
    }

    public function test_booking_options_can_be_loaded_for_selected_future_date(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create(['name' => 'Tuesday Clinic']);
        $admin = $this->authenticateForClinic($clinic);
        [$doctor, $profile] = $this->createActiveDoctorForClinic($clinic, $admin, ['name' => 'Tuesday Doctor']);

        $this->setClinicWorkingHours($clinic, [
            Carbon::TUESDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $profile->id,
            'day_of_week' => Carbon::TUESDAY,
            'start_time' => '11:00',
            'end_time' => '15:00',
            'is_available' => true,
        ]);

        $response = $this->getJson(route('appointments.booking-options', [
            'date' => '2026-06-16',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.date', '2026-06-16');
        $response->assertJsonPath('data.clinic_options.0.id', $clinic->id);
        $response->assertJsonPath('data.doctors.0.id', $doctor->id);
        $response->assertJsonPath('data.doctors.0.available_periods.0.start_time', '11:00');

        Carbon::setTestNow();
    }

    public function test_booking_options_and_store_use_user_id_not_doctor_profile_id_for_doctor_id(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create(['name' => 'Internal Medicine']);
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctorUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Abdulrahman Afoni',
        ]);
        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'full_name' => 'عبدالرحمن أفوني',
            'specialty' => 'داخلية',
            'is_active' => true,
        ]);

        $this->setClinicWorkingHours($clinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '20:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '17:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);

        $optionsResponse = $this->getJson(route('appointments.booking-options', [
            'clinic_id' => $clinic->id,
            'date' => '2026-06-15',
        ]));

        $optionsResponse->assertOk();
        $optionsResponse->assertJsonPath('data.doctors.0.id', $doctorUser->id);
        $optionsResponse->assertJsonPath('data.doctors.0.doctor_id', $doctorUser->id);
        $optionsResponse->assertJsonPath('data.doctors.0.doctor_profile_id', $doctorProfile->id);
        $optionsResponse->assertJsonPath('data.doctors.0.full_name', 'عبدالرحمن أفوني');
        $optionsResponse->assertJsonPath('data.doctors.0.start_time', '17:00');
        $optionsResponse->assertJsonPath('data.doctors.0.end_time', '20:00');

        $storeResponse = $this->postJson(route('appointments.store'), [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'appointment_number' => 'APT-USER-ID-001',
            'scheduled_for' => '2026-06-15T17:30:00+00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $storeResponse->assertCreated();
        $storeResponse->assertJsonPath('data.doctor_id', $doctorUser->id);

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_store_rejects_appointment_on_inactive_clinic_day(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor');
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,

        ]);
        $this->setDepartmentWorkingHours($department, [
            'monday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $nextSaturday = Carbon::now()->next(Carbon::SATURDAY)->format('Y-m-d');

        $response = $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => "{$nextSaturday}T10:00:00+00:00",
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_for']);
    }

    public function test_store_rejects_appointment_outside_clinic_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor');
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,

        ]);
        $this->setDepartmentWorkingHours($department, [
            'saturday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $nextSaturday = Carbon::now()->next(Carbon::SATURDAY)->format('Y-m-d');

        $response = $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => "{$nextSaturday}T18:00:00+00:00",
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['scheduled_for']);
    }

    public function test_store_allows_appointment_inside_clinic_working_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-29 08:00:00'));

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor');
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,

        ]);
        $this->setDepartmentWorkingHours($department, [
            'monday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        $response = $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15T10:00:00+00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertCreated();

        Carbon::setTestNow();
    }

    public function test_store_uses_selected_clinic_for_doctor_schedule_validation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));

        $userClinic = Clinic::factory()->create();
        $selectedClinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($userClinic);
        $patient = Patient::factory()->create(['clinic_id' => $userClinic->id]);
        [$doctor, $doctorProfile] = $this->createActiveDoctorForClinic($selectedClinic, $admin);

        $this->setClinicWorkingHours($selectedClinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        DoctorSchedule::query()->create([
            'clinic_id' => $selectedClinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->postJson(route('appointments.store'), [
            'clinic_id' => $selectedClinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-SELECTED-CLINIC',
            'scheduled_for' => '2026-06-29T10:30:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $selectedClinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-SELECTED-CLINIC',
        ]);

        Carbon::setTestNow();
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $matchingAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-SRCH-100',
        ]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-OTHER-200',
        ]);

        $response = $this->getJson(route('appointments.index', ['search' => 'SRCH']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingAppointment->id);
    }

    public function test_index_filters_by_patient_file_number_doctor_and_date_range(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $otherDoctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);
        app(AssignUserRoleAction::class)->handle($otherDoctor, 'doctor', $user->id);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $otherDoctor->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 123,
        ]);
        $otherPatient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 999,
        ]);

        $matchingAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15 10:00:00',
        ]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'scheduled_for' => '2026-06-15 10:00:00',
        ]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-07-15 10:00:00',
        ]);

        $response = $this->getJson(route('appointments.index', [
            'search' => '123',
            'doctor_id' => $doctor->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingAppointment->id);
        $response->assertJsonPath('data.0.patient.file_number', 123);
    }

    public function test_index_clears_search_filter_when_empty_search_is_passed(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $matchingAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-SRCH-100',
        ]);

        $otherAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-OTHER-200',
        ]);

        $filteredResponse = $this->getJson(route('appointments.index', ['search' => 'SRCH']));

        $filteredResponse->assertOk();
        $filteredResponse->assertJsonCount(1, 'data');
        $filteredResponse->assertJsonPath('data.0.id', $matchingAppointment->id);

        $clearedResponse = $this->getJson(route('appointments.index', [
            'search' => '',
            'status' => '',
            'sort_by' => 'scheduled_for',
            'sort_direction' => 'desc',
            'per_page' => 15,
            'page' => 1,
        ]));

        $clearedResponse->assertOk();
        $clearedResponse->assertJsonCount(2, 'data');
        $clearedResponse->assertJsonFragment(['id' => $matchingAppointment->id]);
        $clearedResponse->assertJsonFragment(['id' => $otherAppointment->id]);
    }

    public function test_index_applies_sorting_by_appointment_number(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $firstAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-100',
        ]);

        $secondAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-200',
        ]);

        $ascResponse = $this->getJson(route('appointments.index', [
            'sort_by' => 'appointment_number',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $firstAppointment->id);
        $ascResponse->assertJsonPath('data.1.id', $secondAppointment->id);

        $descResponse = $this->getJson(route('appointments.index', [
            'sort_by' => 'appointment_number',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $secondAppointment->id);
        $descResponse->assertJsonPath('data.1.id', $firstAppointment->id);
    }

    public function test_doctor_index_returns_only_assigned_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherDoctor = User::factory()->create(['clinic_id' => $clinic->id]);

        $assignedAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('appointments.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $assignedAppointment->id);
    }

    public function test_show_returns_404_for_appointment_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);
        $appointment = Appointment::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
        ]);

        $response = $this->getJson(route('appointments.show', ['appointmentId' => $appointment->id]));

        $response->assertNotFound();
    }

    public function test_update_changes_non_terminal_appointment_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'duration_minutes' => 30,
            'scheduled_for' => now()->addHours(2)->seconds(0)->millisecond(0)->toDateTimeString(),
        ]);

        $response = $this->putJson(
            route('appointments.update', ['appointmentId' => $appointment->id]),
            [
                'doctor_id' => $doctor->id,
                'duration_minutes' => 60,
                'notes' => 'Updated notes',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.duration_minutes', 60);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'duration_minutes' => 60,
            'notes' => 'Updated notes',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'appointments.update',
            'auditable_id' => $appointment->id,
        ]);
    }

    public function test_status_transition_rejects_invalid_state(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        $response = $this->patchJson(
            route('appointments.transition-status', ['appointmentId' => $appointment->id]),
            ['status' => Appointment::STATUS_SCHEDULED],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_status_transition_allows_scheduled_to_arrived(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $response = $this->patchJson(
            route('appointments.transition-status', ['appointmentId' => $appointment->id]),
            ['status' => Appointment::STATUS_ARRIVED],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', Appointment::STATUS_ARRIVED);

        $appointment->refresh();
        $this->assertNotNull($appointment->arrived_at);
    }

    public function test_status_transition_allows_scheduled_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $response = $this->patchJson(
            route('appointments.transition-status', ['appointmentId' => $appointment->id]),
            ['status' => Appointment::STATUS_CANCELED, 'cancel_reason' => 'طلب المريض'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', Appointment::STATUS_CANCELED);

        $appointment->refresh();
        $this->assertNotNull($appointment->canceled_at);
        $this->assertEquals('طلب المريض', $appointment->cancel_reason);
    }

    public function test_status_transition_follows_defined_workflow_and_sets_timestamps(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'arrived_at' => null,
            'completed_at' => null,
        ]);

        $this->patchJson(
            route('appointments.transition-status', ['appointmentId' => $appointment->id]),
            ['status' => Appointment::STATUS_CONFIRMED],
        )->assertOk();

        $this->patchJson(
            route('appointments.transition-status', ['appointmentId' => $appointment->id]),
            ['status' => Appointment::STATUS_ARRIVED],
        )->assertOk();

        $completeResponse = $this->patchJson(
            route('appointments.transition-status', ['appointmentId' => $appointment->id]),
            ['status' => Appointment::STATUS_COMPLETED],
        );

        $completeResponse->assertOk();
        $completeResponse->assertJsonPath('data.status', Appointment::STATUS_COMPLETED);

        $appointment->refresh();

        $this->assertNotNull($appointment->arrived_at);
        $this->assertNotNull($appointment->completed_at);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'appointments.transition_status',
            'auditable_id' => $appointment->id,
        ]);
    }

    public function test_destroy_deletes_only_scheduled_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $scheduledAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $response = $this->deleteJson(
            route('appointments.destroy', ['appointmentId' => $scheduledAppointment->id]),
        );

        $response->assertNoContent();
        $this->assertSoftDeleted($scheduledAppointment);

        $arrivedAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_ARRIVED,
        ]);

        $rejectResponse = $this->deleteJson(
            route('appointments.destroy', ['appointmentId' => $arrivedAppointment->id]),
        );

        $rejectResponse->assertStatus(422);
        $rejectResponse->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('appointments', [
            'id' => $arrivedAppointment->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'appointments.delete',
            'auditable_id' => $scheduledAppointment->id,
        ]);
    }

    public function test_bulk_destroy_deletes_only_scheduled_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $scheduledAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $arrivedAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_ARRIVED,
        ]);

        $response = $this->deleteJson(route('appointments.bulk-destroy'), [
            'ids' => [$scheduledAppointment->id, $arrivedAppointment->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($scheduledAppointment);
        $this->assertDatabaseHas('appointments', ['id' => $arrivedAppointment->id]);
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
     * @param  array<string, mixed>  $userAttributes
     * @return array{0: User, 1: DoctorProfile}
     */
    private function createActiveDoctorForClinic(Clinic $clinic, User $actor, array $userAttributes = []): array
    {
        $doctor = User::factory()->create([
            'clinic_id' => $clinic->id,
            ...$userAttributes,
        ]);

        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $actor->id);

        $profile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);

        return [$doctor, $profile];
    }

    /**
     * @param  array<int, array{start_time: string, end_time: string}>  $activeDays
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

    /**
     * @param  array<string, array{start_time: string, end_time: string}>  $activeDays
     */
    private function setDepartmentWorkingHours(Department $department, array $activeDays): void
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

        foreach (ClinicWorkingHour::DAYS as $day) {
            $hours = $normalized[$day] ?? null;

            ClinicWorkingHour::query()->create([
                'clinic_id' => $department->clinic_id,
                'day_of_week' => $day,
                'is_active' => $hours !== null,
                'start_time' => $hours['start_time'] ?? null,
                'end_time' => $hours['end_time'] ?? null,
            ]);
        }
    }
}
