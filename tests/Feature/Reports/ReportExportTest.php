<?php

namespace Tests\Feature\Reports;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_export_reports_as_excel_and_pdf(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'accountant');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'total_amount' => 200,
            'paid_amount' => 150,
            'balance_amount' => 50,
        ]);

        Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'status' => Payment::STATUS_RECORDED,
            'amount' => 150,
            'refund_amount' => 0,
            'paid_at' => now(),
        ]);

        $excelResponse = $this->get(route('reports.export.excel'));
        $excelResponse->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $excelResponse->headers->get('content-type'));
        $this->assertStringContainsString('MCMS Reports Export', $excelResponse->streamedContent());

        $pdfResponse = $this->get(route('reports.export.pdf'));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdfResponse->getContent());
    }

    public function test_user_without_report_permissions_cannot_export_reports(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $this->get(route('reports.export.excel'))->assertForbidden();
        $this->get(route('reports.export.pdf'))->assertForbidden();
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
