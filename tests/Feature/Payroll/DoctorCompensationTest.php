<?php

namespace Tests\Feature\Payroll;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DoctorCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_doctor_creates_entitlement_on_appointment(): void
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
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
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
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100000,
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();

        $appointment = Appointment::query()->latest()->first();

        $this->assertDatabaseHas('doctor_appointment_entitlements', [
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => 100000,
            'percentage' => 40,
            'entitlement_amount' => 40000,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
        ]);
    }

    public function test_weekly_fixed_doctor_does_not_create_entitlement(): void
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
            'compensation_type' => DoctorProfile::COMPENSATION_WEEKLY_FIXED,
            'compensation_value' => 300000,
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
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100000,
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();

        $appointment = Appointment::query()->latest()->first();

        $this->assertDatabaseMissing('doctor_appointment_entitlements', [
            'appointment_id' => $appointment->id,
        ]);
    }

    public function test_monthly_fixed_doctor_does_not_create_entitlement(): void
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
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
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
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 100000,
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();

        $appointment = Appointment::query()->latest()->first();

        $this->assertDatabaseMissing('doctor_appointment_entitlements', [
            'appointment_id' => $appointment->id,
        ]);
    }

    public function test_payroll_calculates_percentage_doctor_from_entitlements(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment1 = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-15T10:00:00',
            'status' => Appointment::STATUS_COMPLETED,
            'cost' => 100000,
        ]);

        $appointment2 = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-20T11:00:00',
            'status' => Appointment::STATUS_COMPLETED,
            'cost' => 150000,
        ]);

        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment1->id,
            'appointment_cost' => 100000,
            'percentage' => 40,
            'entitlement_amount' => 40000,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-15',
        ]);

        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment2->id,
            'appointment_cost' => 150000,
            'percentage' => 40,
            'entitlement_amount' => 60000,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-20',
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $this->assertDatabaseHas('doctor_monthly_dues', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctorProfile->id,
            'salary_month' => '2026-06',
            'payment_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'percentage' => 40,
            'due_amount' => 100000,
            'status' => DoctorMonthlyDue::STATUS_UNPAID,
        ]);
    }

    public function test_payroll_calculates_weekly_fixed_doctor(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_WEEKLY_FIXED,
            'compensation_value' => 300000,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $this->assertDatabaseHas('doctor_monthly_dues', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctorProfile->id,
            'salary_month' => '2026-06',
            'payment_type' => DoctorProfile::COMPENSATION_WEEKLY_FIXED,
            'fixed_weekly_amount' => 300000,
            'due_amount' => 300000,
            'status' => DoctorMonthlyDue::STATUS_UNPAID,
        ]);
    }

    public function test_payroll_calculates_monthly_fixed_doctor(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();

        $this->assertDatabaseHas('doctor_monthly_dues', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctorProfile->id,
            'salary_month' => '2026-06',
            'payment_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'fixed_monthly_amount' => 1500000,
            'due_amount' => 1500000,
            'status' => DoctorMonthlyDue::STATUS_UNPAID,
        ]);
    }

    public function test_financial_page_shows_appointment_cost_not_entitlement(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-28T10:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 100000,
        ]);

        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => 100000,
            'percentage' => 40,
            'entitlement_amount' => 40000,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-28',
        ]);

        $response = $this->getJson(route('financial.index'));

        $response->assertOk();
        $response->assertJsonPath('financial_rows.0.cost', 100000);
        $response->assertJsonMissing(['entitlement_amount' => 40000]);
    }

    public function test_no_duplicate_monthly_dues_for_same_doctor_and_month(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
            'is_active' => true,
        ]);

        $this->getJson(route('salaries.index', ['month' => '2026-06']));
        $this->getJson(route('salaries.index', ['month' => '2026-06']));

        $count = DoctorMonthlyDue::query()
            ->forClinic($clinic->id)
            ->where('doctor_id', $doctorProfile->id)
            ->where('salary_month', '2026-06')
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_cannot_change_compensation_type_with_unpaid_entitlements(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);

        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-28T10:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 100000,
        ]);

        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => 100000,
            'percentage' => 40,
            'entitlement_amount' => 40000,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-28',
        ]);

        $response = $this->putJson(route('doctors.update', $doctorProfile->id), [
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500000,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('compensation_type');
        $response->assertJsonFragment(['لا يمكن تغيير نوع الأجر بينما توجد مستحقات غير مدفوعة. يجب تسوية المستحقات أولاً.']);

        $doctorProfile->refresh();
        $this->assertEquals(DoctorProfile::COMPENSATION_PERCENTAGE, $doctorProfile->compensation_type);
    }

    public function test_entitlement_stores_compensation_snapshot(): void
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
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 30,
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
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 50000,
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('doctor_appointment_entitlements', [
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 30,
            'entitlement_amount' => 15000,
        ]);
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'admin'): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
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
}
