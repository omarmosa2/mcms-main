<?php

namespace Tests\Feature\Payroll;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorPayment;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\EmployeeMonthlySalary;
use App\Models\EmployeeSalaryPayment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->has('clinics', 1)
            ->where('clinics.0.id', $clinic->id)
            ->missing('departments')
            ->where('employee_salaries.0.name', 'Payroll Employee')
            ->where('employee_salaries.0.clinic', $clinic->name)
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

        $salaryPayment = EmployeeSalaryPayment::query()
            ->where('employee_monthly_salary_id', $monthlySalary->id)
            ->first();

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $clinic->id,
            'invoice_id' => null,
            'received_by' => $this->app['auth']->id(),
            'method' => 'cash',
            'status' => Payment::STATUS_RECORDED,
            'amount' => 1000,
            'payable_type' => EmployeeSalaryPayment::class,
            'payable_id' => $salaryPayment->id,
        ]);
        $this->assertNotNull($salaryPayment->payment_id);

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
            'is_active' => true,
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
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-02',
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']));

        $response->assertOk();
        $response->assertJsonPath('doctor_dues.0.due_amount', 400);
        $response->assertJsonPath('doctor_dues.0.payment_type', 'percentage');
        $response->assertJsonPath('doctor_dues.0.visits_count', 1);

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
        Carbon::setTestNow('2026-06-29 09:00:00');

        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'is_active' => true,
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
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
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
        $this->assertDatabaseHas('doctor_payments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'payment_type' => DoctorPayment::TYPE_PERCENTAGE,
            'period_start' => '2026-06-01 00:00:00',
            'period_end' => '2026-06-30 00:00:00',
            'amount' => 400,
        ]);

        $doctorPayment = DoctorPayment::query()
            ->where('doctor_id', $doctor->id)
            ->first();

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $clinic->id,
            'invoice_id' => null,
            'received_by' => $admin->id,
            'method' => 'cash',
            'status' => Payment::STATUS_RECORDED,
            'amount' => 400,
            'payable_type' => DoctorPayment::class,
            'payable_id' => $doctorPayment->id,
        ]);
        $this->assertNotNull($doctorPayment->payment_id);
        $this->assertDatabaseHas('doctor_appointment_entitlements', [
            'doctor_profile_id' => $doctor->id,
            'status' => DoctorAppointmentEntitlement::STATUS_PAID,
        ]);

        $monthlyDue->refresh();
        $this->assertEquals(400, $monthlyDue->paid_amount);
        $this->assertEquals(0, $monthlyDue->remaining_amount);
        $this->assertEquals('paid', $monthlyDue->status);
    }

    public function test_admin_can_pay_doctor_due_from_another_visible_clinic(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');

        $adminClinic = Clinic::factory()->create();
        $doctorClinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($adminClinic);
        $doctorUser = User::factory()->create(['clinic_id' => $doctorClinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $doctorClinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 500,
            'is_active' => true,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlyDue = DoctorMonthlyDue::query()
            ->withoutGlobalScope('clinic')
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
        $this->assertDatabaseHas('doctor_payments', [
            'clinic_id' => $doctorClinic->id,
            'doctor_id' => $doctor->id,
            'payment_type' => DoctorPayment::TYPE_MONTHLY,
            'amount' => 500,
        ]);
        $this->assertDatabaseHas('payments', [
            'clinic_id' => $doctorClinic->id,
            'invoice_id' => null,
            'received_by' => $admin->id,
            'method' => 'cash',
            'status' => Payment::STATUS_RECORDED,
            'amount' => 500,
        ]);

        $monthlyDue->refresh();
        $this->assertEquals(500, $monthlyDue->paid_amount);
        $this->assertEquals(0, $monthlyDue->remaining_amount);
        $this->assertEquals(DoctorMonthlyDue::STATUS_PAID, $monthlyDue->status);
    }

    public function test_legacy_doctor_payment_endpoint_uses_the_salary_payment_handler(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'is_active' => true,
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
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
            'status' => DoctorAppointmentEntitlement::STATUS_UNPAID,
            'appointment_date' => '2026-06-02',
        ]);

        $this->get('/doctor-payments')->assertRedirect(route('salaries.index'));
        $this->get('/salaries/doctor-payments')->assertRedirect(route('salaries.index'));
        $this->get('/financial/doctor-payments')->assertRedirect(route('salaries.index'));
        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlyDue = DoctorMonthlyDue::query()
            ->where('doctor_id', $doctor->id)
            ->where('salary_month', '2026-06')
            ->first();

        $response = $this->postJson(route('legacy.salaries.doctor-payments.store'), [
            'doctor_monthly_due_id' => $monthlyDue->id,
            'amount' => 250,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-06',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('doctor_payments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'payment_type' => DoctorPayment::TYPE_PERCENTAGE,
            'amount' => 400,
        ]);

        $this->postJson(route('legacy.financial.doctor-payments.store'), [
            'doctor_monthly_due_id' => $monthlyDue->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-07',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('doctor_monthly_due_id');
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
            'compensation_type' => DoctorProfile::COMPENSATION_WEEKLY_FIXED,
            'compensation_value' => 300,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']));

        $response->assertOk();
        $doctorDue = $response->json('doctor_dues.0');
        $this->assertEquals('fixed_weekly', $doctorDue['payment_type']);
        $this->assertEquals(300, $doctorDue['fixed_weekly_amount']);
        $this->assertEquals(300, $doctorDue['due_amount']);
    }

    public function test_doctor_monthly_due_calculation(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']));

        $response->assertOk();
        $response->assertJsonPath('doctor_dues.0.due_amount', 1500);
        $response->assertJsonPath('doctor_dues.0.payment_type', 'fixed_monthly');
        $response->assertJsonPath('doctor_dues.0.fixed_monthly_amount', 1500);
    }

    public function test_weekly_doctor_payment_can_only_be_recorded_once_for_current_week(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_WEEKLY_FIXED,
            'compensation_value' => 300,
            'is_active' => true,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlyDue = DoctorMonthlyDue::query()
            ->where('doctor_id', $doctor->id)
            ->where('salary_month', '2026-06')
            ->first();

        $payload = [
            'doctor_monthly_due_id' => $monthlyDue->id,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-29',
        ];

        $this->postJson(route('salaries.doctor-payments.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('doctor_payments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'payment_type' => DoctorPayment::TYPE_WEEKLY,
            'period_start' => '2026-06-29 00:00:00',
            'period_end' => '2026-07-05 00:00:00',
            'amount' => 300,
        ]);

        $this->postJson(route('salaries.doctor-payments.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('doctor_monthly_due_id');
    }

    public function test_monthly_doctor_payment_can_only_be_recorded_once_for_current_month(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            'compensation_value' => 1500,
            'is_active' => true,
        ]);

        $this->get(route('salaries.index', ['month' => '2026-06']));

        $monthlyDue = DoctorMonthlyDue::query()
            ->where('doctor_id', $doctor->id)
            ->where('salary_month', '2026-06')
            ->first();

        $payload = [
            'doctor_monthly_due_id' => $monthlyDue->id,
            'payment_method' => 'cash',
            'payment_date' => '2026-06-29',
        ];

        $this->postJson(route('salaries.doctor-payments.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('doctor_payments', [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'payment_type' => DoctorPayment::TYPE_MONTHLY,
            'period_start' => '2026-06-01 00:00:00',
            'period_end' => '2026-06-30 00:00:00',
            'amount' => 1500,
        ]);

        $this->postJson(route('salaries.doctor-payments.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('doctor_monthly_due_id');
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
