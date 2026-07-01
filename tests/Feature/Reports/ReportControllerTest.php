<?php

namespace Tests\Feature\Reports;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorSalaryPayment;
use App\Models\EmployeeSalaryPayment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_can_view_operational_report_page(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->get(route('reports.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('can_view_operational', true)
            ->where('can_view_financial', true)
            ->missing('operational_summary')
            ->missing('financial_summary')
            ->loadDeferredProps('reports', fn (Assert $page) => $page
                ->has('operational_summary')
                ->has('financial_summary')
                ->has('doctor_performance')
                ->has('diagnostics_summary')
                ->has('report_data')
                ->has('chart_data')));
    }

    public function test_admin_can_view_financial_report_page(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'admin');

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $otherPatient = Patient::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'total_amount' => 300,
            'paid_amount' => 250,
            'balance_amount' => 50,
            'created_at' => now(),
        ]);

        Invoice::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 1000,
            'paid_amount' => 1000,
            'balance_amount' => 0,
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'status' => Payment::STATUS_REFUNDED,
            'amount' => 250,
            'refund_amount' => 50,
            'paid_at' => now(),
            'refunded_at' => now(),
        ]);

        $response = $this->get(route('reports.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('can_view_operational', true)
            ->where('can_view_financial', true)
            ->missing('operational_summary')
            ->missing('financial_summary')
            ->loadDeferredProps('reports', fn (Assert $page) => $page
                ->where('financial_summary.invoices.total_amount', 300)
                ->where('financial_summary.payments.gross_collections', 250)
                ->where('financial_summary.payments.refund_amount', 50)
                ->where('financial_summary.payments.net_collections', 200)
                ->has('financial_statements')
                ->has('report_data.financial')
                ->has('chart_data.daily_income')));

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'reports.financial',
        ]);
    }

    public function test_json_report_response_is_scoped_by_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $otherPatient = Patient::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_for' => '2026-04-15 09:00:00',
        ]);

        Appointment::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_for' => '2026-04-15 09:00:00',
        ]);

        $response = $this->getJson(route('reports.index', [
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.can_view_operational', true);
        $response->assertJsonPath('data.can_view_financial', true);
        $response->assertJsonPath('data.operational_summary.patients_total', 1);
        $response->assertJsonPath('data.operational_summary.appointments.total', 1);
        $response->assertJsonPath('data.report_data.overview.appointments_total', 1);
        $response->assertJsonCount(1, 'data.chart_data.appointments_by_day');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'reports.view',
        ]);
    }

    public function test_each_reports_tab_returns_its_payload_and_financial_charts(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        foreach (['overview', 'financial', 'payroll', 'clinics', 'doctors', 'appointments', 'patients', 'expenses', 'pharmacy'] as $reportType) {
            $response = $this->getJson(route('reports.index', [
                'from' => '2026-04-01',
                'to' => '2026-04-30',
                'report_type' => $reportType,
            ]));

            $response->assertOk();
            $response->assertJsonPath('data.filters.report_type', $reportType);
            $response->assertJsonStructure([
                'data' => [
                    'report_data' => [$reportType],
                ],
            ]);
        }

        $financialResponse = $this->getJson(route('reports.index', [
            'from' => '2026-04-01',
            'to' => '2026-04-30',
            'report_type' => 'financial',
        ]));

        $financialResponse->assertOk();
        $financialResponse->assertJsonStructure([
            'data' => [
                'chart_data' => [
                    'daily_income',
                    'income_by_clinic',
                    'income_by_doctor',
                    'expenses_by_category',
                    'monthly_profit',
                ],
            ],
        ]);
    }

    public function test_financial_statements_include_new_employee_and_doctor_payroll_payments(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'admin');

        EmployeeSalaryPayment::factory()->create([
            'clinic_id' => $clinic->id,
            'amount' => 700,
            'payment_date' => '2026-06-05',
        ]);
        DoctorSalaryPayment::factory()->create([
            'clinic_id' => $clinic->id,
            'amount_paid' => 300,
            'paid_at' => '2026-06-06',
        ]);

        $response = $this->getJson(route('reports.index', [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.financial_statements.income_statement.payroll_expenses', 1000);
        $response->assertJsonPath('data.financial_statements.cash_flow.cash_outflow', 1000);
    }

    public function test_user_without_reports_permissions_gets_forbidden(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $response = $this->get(route('reports.index'));

        $response->assertForbidden();
    }

    public function test_accountant_is_forbidden_from_admin_reports_page(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'accountant');

        $this->get(route('reports.index'))->assertForbidden();
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
