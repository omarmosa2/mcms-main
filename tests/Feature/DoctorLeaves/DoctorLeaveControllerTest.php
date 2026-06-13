<?php

namespace Tests\Feature\DoctorLeaves;

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorLeaveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_full_day_leave_and_reports_existing_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15 10:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $response = $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => '2026-06-15',
            'reason' => 'Conference',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', DoctorLeave::TYPE_FULL_DAY);
        $response->assertJsonPath('data.appointments_count', 1);

        $this->assertDatabaseHas('doctor_leaves', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => '2026-06-15 00:00:00',
            'status' => DoctorLeave::STATUS_ACTIVE,
        ]);
    }

    public function test_store_rejects_hourly_leave_without_times_and_overlaps(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time', 'end_time']);

        DoctorLeave::factory()->hourly('10:00', '12:00')->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'leave_date' => '2026-06-16',
        ]);

        $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
            'start_time' => '11:00',
            'end_time' => '13:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_date']);

        $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ])->assertCreated();
    }

    public function test_appointments_cannot_be_booked_inside_active_doctor_leave(): void
    {
        Carbon::setTestNow('2026-06-13 08:00:00');

        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $leaveDate = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $leaveDay = strtolower(Carbon::parse($leaveDate)->format('l'));
        $leaveDayOfWeek = Carbon::parse($leaveDate)->dayOfWeek;

        $this->setDepartmentWorkingHours($department, $leaveDay, '09:00', '17:00');
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $leaveDayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        DoctorLeave::factory()->hourly('10:00', '12:00')->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'leave_date' => $leaveDate,
        ]);

        $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => "{$leaveDate}T10:30:00+00:00",
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_for']);

        $this->postJson(route('appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => "{$leaveDate}T12:30:00+00:00",
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100,
        ])->assertCreated();

        Carbon::setTestNow();
    }

    public function test_daily_schedule_hides_department_when_all_doctors_have_full_day_leave(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);
        $leaveDate = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $leaveDay = strtolower(Carbon::parse($leaveDate)->format('l'));
        $leaveDayOfWeek = Carbon::parse($leaveDate)->dayOfWeek;

        $this->setDepartmentWorkingHours($department, $leaveDay, '09:00', '17:00');
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => $leaveDayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);
        DoctorLeave::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => $leaveDate,
        ]);

        $response = $this->get(route('daily-schedule.index', ['date' => $leaveDate]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('daily-schedule/Index')
            ->where('scheduleData.clinics', [])
        );
    }

    public function test_daily_schedule_uses_available_alternative_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);
        [, $alternativeDoctor] = $this->createDoctorInDepartment($clinic, $admin, $department);
        $leaveDate = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $leaveDay = strtolower(Carbon::parse($leaveDate)->format('l'));
        $leaveDayOfWeek = Carbon::parse($leaveDate)->dayOfWeek;

        $this->setDepartmentWorkingHours($department, $leaveDay, '09:00', '17:00');

        foreach ([$doctor, $alternativeDoctor] as $scheduledDoctor) {
            DoctorSchedule::query()->create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $scheduledDoctor->id,
                'day_of_week' => $leaveDayOfWeek,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_available' => true,
            ]);
        }

        DoctorLeave::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => $leaveDate,
        ]);

        $response = $this->get(route('daily-schedule.index', ['date' => $leaveDate]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('daily-schedule/Index')
            ->where('scheduleData.clinics.0.id', $department->id)
            ->where('scheduleData.clinics.0.doctors.0.doctor_id', $alternativeDoctor->id)
        );
    }

    public function test_store_rejects_leave_when_doctor_has_no_schedule_on_that_day(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        $response = $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => '2026-06-15',
            'reason' => 'Vacation',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['doctor_id']);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('doctor_id', $errors);
        $this->assertStringContainsString('لا يمكن تسجيل إجازة لهذا الطبيب', $errors['doctor_id'][0]);

        $this->assertDatabaseMissing('doctor_leaves', [
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_store_rejects_hourly_leave_when_doctor_has_no_schedule_on_that_day(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        $response = $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['doctor_id']);

        $errors = $response->json('errors');
        $this->assertStringContainsString('لا يمكن تسجيل إجازة لهذا الطبيب', $errors['doctor_id'][0]);

        $this->assertDatabaseMissing('doctor_leaves', [
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_store_rejects_hourly_leave_outside_doctor_schedule_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
            'start_time' => '15:00',
            'end_time' => '16:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $errors = $response->json('errors');
        $this->assertStringContainsString('وقت الإجازة الساعية يجب أن يكون ضمن ساعات دوام الطبيب الأساسية', $errors['start_time'][0]);

        $this->assertDatabaseMissing('doctor_leaves', [
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_store_rejects_hourly_leave_partially_outside_doctor_schedule(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $response = $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $errors = $response->json('errors');
        $this->assertStringContainsString('وقت الإجازة الساعية يجب أن يكون ضمن ساعات دوام الطبيب الأساسية', $errors['start_time'][0]);

        $this->assertDatabaseMissing('doctor_leaves', [
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_store_allows_hourly_leave_within_doctor_schedule(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '14:00',
            'is_available' => true,
        ]);

        $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'leave_date' => '2026-06-16',
            'start_time' => '09:00',
            'end_time' => '14:00',
        ])->assertCreated();

        $this->assertDatabaseHas('doctor_leaves', [
            'doctor_id' => $doctor->id,
            'type' => DoctorLeave::TYPE_HOURLY,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);
    }

    public function test_store_allows_full_day_leave_when_schedule_exists(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        [$department, $doctor] = $this->createDoctorInDepartment($clinic, $admin);

        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $this->postJson(route('doctor-leaves.store'), [
            'doctor_id' => $doctor->id,
            'department_id' => $department->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
            'leave_date' => '2026-06-15',
        ])->assertCreated();

        $this->assertDatabaseHas('doctor_leaves', [
            'doctor_id' => $doctor->id,
            'type' => DoctorLeave::TYPE_FULL_DAY,
        ]);
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
     * @return array{0: Department, 1: User}
     */
    private function createDoctorInDepartment(Clinic $clinic, User $admin, ?Department $department = null): array
    {
        $department ??= Department::factory()->create([
            'clinic_id' => $clinic->id,
            'is_active' => true,
        ]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);

        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $admin->id);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'department_id' => $department->id,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        return [$department, $doctor];
    }

    private function setDepartmentWorkingHours(Department $department, string $day, string $startTime, string $endTime): void
    {
        ClinicWorkingHour::query()->create([
            'department_id' => $department->id,
            'day_of_week' => $day,
            'is_active' => true,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }
}
