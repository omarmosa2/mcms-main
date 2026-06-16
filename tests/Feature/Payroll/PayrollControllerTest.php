<?php

namespace Tests\Feature\Payroll;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\EmployeeMonthlySalary;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_payroll_page_with_auto_created_employee_monthly_salaries(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Payroll Employee',
            'base_salary' => 1500,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('salaries/Index')
            ->where('employee_salaries.0.name', 'Payroll Employee')
            ->where('employee_salaries.0.status', 'unpaid')
            ->where('employee_salaries.0.due_amount', 1500)
            ->where('employee_salaries.0.remaining_amount', 1500)
            ->where('summaries.employee_due', 1500)
            ->where('summaries.employee_remaining', 1500)
            ->where('summaries.employee_count', 1)
            ->where('summaries.employee_unpaid_count', 1)
            ->where('summaries.total_count', 1)
        );

        $this->assertDatabaseHas('employee_monthly_salaries', [
            'clinic_id' => $clinic->id,
            'salary_month' => '2026-06',
            'due_amount' => 1500,
            'status' => 'unpaid',
        ]);
    }

    public function test_non_admin_cannot_access_payroll_page(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'accountant');

        $this->get(route('salaries.index'))->assertForbidden();
    }

    public function test_employee_salary_payment_must_pay_the_full_monthly_amount_once(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'base_salary' => 1000,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlySalary = EmployeeMonthlySalary::query()
            ->where('employee_id', $employee->id)
            ->where('salary_month', '2026-06')
            ->first();

        $this->postJson(route('salaries.employee-payments.store'), [
            'employee_monthly_salary_id' => $monthlySalary->id,
            'amount' => 400,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-05',
            'notes' => 'First installment',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $response = $this->postJson(route('salaries.employee-payments.store'), [
            'employee_monthly_salary_id' => $monthlySalary->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-05',
            'notes' => 'Monthly salary payment',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employee_salary_payments', [
            'clinic_id' => $clinic->id,
            'employee_monthly_salary_id' => $monthlySalary->id,
            'employee_id' => $employee->id,
            'salary_month' => '2026-06',
            'amount' => 1000,
        ]);

        $monthlySalary->refresh();
        $this->assertEquals(1000, $monthlySalary->paid_amount);
        $this->assertEquals(0, $monthlySalary->remaining_amount);
        $this->assertEquals('paid', $monthlySalary->status);
    }

    public function test_employee_full_payment_updates_status_to_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'base_salary' => 1000,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlySalary = EmployeeMonthlySalary::query()
            ->where('employee_id', $employee->id)
            ->where('salary_month', '2026-06')
            ->first();

        $this->postJson(route('salaries.employee-payments.store'), [
            'employee_monthly_salary_id' => $monthlySalary->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-05',
        ]);

        $monthlySalary->refresh();
        $this->assertEquals(1000, $monthlySalary->paid_amount);
        $this->assertEquals(0, $monthlySalary->remaining_amount);
        $this->assertEquals('paid', $monthlySalary->status);
    }

    public function test_doctor_percentage_due_is_auto_created_monthly(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'scheduled_for' => '2026-06-02 09:00:00',
            'cost' => 1000,
            'appointment_type' => 'first_visit',
        ]);
        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctor->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => 1000,
            'percentage' => 40,
            'entitlement_amount' => 400,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-02',
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']));

        $response->assertOk();
        $response->assertJsonPath('doctor_dues.0.due_amount', 400);
        $response->assertJsonPath('doctor_dues.0.payment_type', 'percentage');

        $this->assertDatabaseHas('doctor_monthly_dues', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'salary_month' => '2026-06',
            'payment_type' => 'percentage',
            'due_amount' => 400,
            'status' => 'unpaid',
        ]);
    }

    public function test_doctor_payment_updates_monthly_due_record(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'scheduled_for' => '2026-06-02 09:00:00',
            'cost' => 1000,
            'appointment_type' => 'first_visit',
        ]);
        DoctorAppointmentEntitlement::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctor->id,
            'appointment_id' => $appointment->id,
            'appointment_cost' => 1000,
            'percentage' => 40,
            'entitlement_amount' => 400,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-02',
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlyDue = DoctorMonthlyDue::query()
            ->where('doctor_id', $doctor->id)
            ->where('salary_month', '2026-06')
            ->first();

        $response = $this->postJson(route('salaries.doctor-payments.store'), [
            'doctor_monthly_due_id' => $monthlyDue->id,
            'amount' => 250,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-06',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('doctor_due_payments', [
            'clinic_id' => $clinic->id,
            'doctor_monthly_due_id' => $monthlyDue->id,
            'doctor_id' => $doctor->id,
            'salary_month' => '2026-06',
            'amount' => 250,
        ]);

        $monthlyDue->refresh();
        $this->assertEquals(250, $monthlyDue->paid_amount);
        $this->assertEquals(150, $monthlyDue->remaining_amount);
        $this->assertEquals('partially_paid', $monthlyDue->status);
    }

    public function test_employee_salary_payment_cannot_be_recorded_twice_for_the_same_month(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'base_salary' => 1000,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlySalary = EmployeeMonthlySalary::query()
            ->where('employee_id', $employee->id)
            ->where('salary_month', '2026-06')
            ->first();

        $this->postJson(route('salaries.employee-payments.store'), [
            'employee_monthly_salary_id' => $monthlySalary->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-05',
        ])->assertCreated();

        $this->postJson(route('salaries.employee-payments.store'), [
            'employee_monthly_salary_id' => $monthlySalary->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-06',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_monthly_salary_id');
    }

    public function test_monthly_records_are_independent_per_month(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'base_salary' => 500,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));
        $this->get(route('salaries.index', ['month' => '2026-07']));

        $this->assertDatabaseHas('employee_monthly_salaries', [
            'clinic_id' => $clinic->id,
            'salary_month' => '2026-06',
            'due_amount' => 500,
        ]);
        $this->assertDatabaseHas('employee_monthly_salaries', [
            'clinic_id' => $clinic->id,
            'salary_month' => '2026-07',
            'due_amount' => 500,
        ]);

        $this->assertEquals(2, EmployeeMonthlySalary::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_doctor_weekly_due_calculation(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_WEEKLY,
            'compensation_value' => 300,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']));

        $response->assertOk();
        $doctorDue = $response->json('doctor_dues.0');
        $this->assertEquals('weekly', $doctorDue['payment_type']);
        $this->assertEquals(300, $doctorDue['fixed_weekly_amount']);
        $this->assertGreaterThan(0, $doctorDue['due_amount']);
    }

    public function test_doctor_monthly_due_calculation(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY,
            'compensation_value' => 1500,
            'status' => DoctorProfile::STATUS_ACTIVE,
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']));

        $response->assertOk();
        $response->assertJsonPath('doctor_dues.0.due_amount', 1500);
        $response->assertJsonPath('doctor_dues.0.payment_type', 'monthly');
        $response->assertJsonPath('doctor_dues.0.fixed_monthly_amount', 1500);
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'clinic_admin'): User
    {
        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
