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

    public function test_index_returns_only_clinic_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $appointment = Appointment::factory()->create([
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
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $appointment->id);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'appointments.index',
        ]);
    }

    public function test_index_passes_only_today_available_departments_and_doctors(): void
    {
        Carbon::setTestNow('2026-06-15 08:00:00');

        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $unavailableDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'is_active' => true,
            'name' => 'Unavailable Clinic',
        ]);
        $availableDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'is_active' => true,
            'name' => 'Available Clinic',
        ]);
        $unavailableDoctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $availableDoctor = User::factory()->create(['clinic_id' => $clinic->id]);

        app(AssignUserRoleAction::class)->handle($unavailableDoctor, 'doctor', $admin->id);
        app(AssignUserRoleAction::class)->handle($availableDoctor, 'doctor', $admin->id);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $unavailableDoctor->id,

            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $availableDoctor->id,

            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $this->setDepartmentWorkingHours($unavailableDepartment, [
            'monday' => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);

        foreach ([$unavailableDoctor, $availableDoctor] as $doctor) {
            DoctorSchedule::query()->create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
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
            ->has('today_availability.departments', 1)
            ->where('today_availability.departments.0', $availableDepartment->id)
            ->has('today_availability.doctors', 1)
            ->where('today_availability.doctors.0.id', $availableDoctor->id)
        );

        Carbon::setTestNow();
    }

    public function test_store_creates_scheduled_appointment_with_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-3000',
            'scheduled_for' => now()->addHours(3)->seconds(0)->millisecond(0)->toISOString(),
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
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));

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
