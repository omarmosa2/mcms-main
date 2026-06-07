<?php

namespace Tests\Feature\Payroll;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_payroll_page_with_employee_rows(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Payroll Employee',
            'base_salary' => 1500,
        ]);

        $response = $this->get(route('salaries.index', ['month' => '2026-06']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('salaries/Index')
            ->where('employee_salaries.0.name', 'Payroll Employee')
            ->where('employee_salaries.0.status', 'unpaid')
            ->where('summaries.employee_due', 1500)
        );
    }

    public function test_non_admin_cannot_access_payroll_page(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'accountant');

        $this->get(route('salaries.index'))->assertForbidden();
    }

    public function test_employee_salary_payment_records_partial_payment(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'base_salary' => 1000,
        ]);

        $response = $this->postJson(route('salaries.employee-payments.store'), [
            'employee_id' => $employee->id,
            'period_month' => '2026-06',
            'amount_paid' => 400,
            'payment_method' => 'cash',
            'paid_at' => '2026-06-05',
            'notes' => 'First installment',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employee_salary_payments', [
            'clinic_id' => $clinic->id,
            'employee_id' => $employee->id,
            'paid_by' => $admin->id,
            'period_month' => '2026-06',
            'amount_due' => 1000,
            'amount_paid' => 400,
        ]);
    }

    public function test_doctor_percentage_due_is_calculated_from_visit_invoices(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->authenticateForClinic($clinic);
        $doctorUser = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'compensation_type' => DoctorProfile::COMPENSATION_PERCENTAGE,
            'compensation_value' => 40,
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

        $this->getJson(route('salaries.index', ['month' => '2026-06', 'person_type' => 'doctor']))
            ->assertOk()
            ->assertJsonPath('doctor_dues.0.amount_due', 400);

        $response = $this->postJson(route('salaries.doctor-payments.store'), [
            'doctor_profile_id' => $doctor->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'amount_paid' => 250,
            'payment_method' => 'cash',
            'paid_at' => '2026-06-06',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('doctor_salary_payments', [
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctor->id,
            'paid_by' => $admin->id,
            'amount_due' => 400,
            'amount_paid' => 250,
        ]);
    }

    public function test_employee_payment_cannot_exceed_remaining_amount(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'base_salary' => 1000,
        ]);
        EmployeeSalaryPayment::factory()->create([
            'clinic_id' => $clinic->id,
            'employee_id' => $employee->id,
            'period_month' => '2026-06',
            'amount_due' => 1000,
            'amount_paid' => 900,
        ]);

        $this->postJson(route('salaries.employee-payments.store'), [
            'employee_id' => $employee->id,
            'period_month' => '2026-06',
            'amount_paid' => 200,
            'paid_at' => '2026-06-06',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount_paid');
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
