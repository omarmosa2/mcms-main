<?php

namespace Tests\Feature\Billing;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_draft_invoice_with_calculated_totals(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $response = $this->postJson(route('billing.invoices.store'), [
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-T-001',
            'notes' => 'Consultation invoice',
            'items' => [
                [
                    'service_code' => 'CONS-1',
                    'description' => 'Consultation',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_amount' => 10,
                    'tax_amount' => 5,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', Invoice::STATUS_DRAFT);
        $response->assertJsonPath('data.subtotal_amount', 200);
        $response->assertJsonPath('data.total_amount', 195);
        $response->assertJsonPath('data.balance_amount', 195);

        $invoiceId = (int) $response->json('data.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'subtotal_amount' => 200.00,
            'discount_amount' => 10.00,
            'tax_amount' => 5.00,
            'total_amount' => 195.00,
            'balance_amount' => 195.00,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'clinic_id' => $clinic->id,
            'service_code' => 'CONS-1',
            'line_total' => 195.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'billing.invoices.create',
            'auditable_id' => $invoiceId,
        ]);
    }

    public function test_issue_transitions_invoice_from_draft_to_issued(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 250,
            'balance_amount' => 250,
            'issued_at' => null,
        ]);

        InvoiceItem::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'description' => 'Consultation',
            'line_total' => 250,
        ]);

        $response = $this->patchJson(route('billing.invoices.issue', ['invoiceId' => $invoice->id]), [
            'due_at' => '2026-04-30',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', Invoice::STATUS_ISSUED);
        $response->assertJsonPath('data.issued_by', $user->id);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_ISSUED,
            'issued_by' => $user->id,
            'due_at' => '2026-04-30 00:00:00',
        ]);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $matchingInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-SRCH-100',
        ]);

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-OTHER-200',
        ]);

        $response = $this->getJson(route('billing.invoices.index', ['search' => 'SRCH']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingInvoice->id);
    }

    public function test_index_applies_sorting_by_invoice_number(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $firstInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-100',
        ]);

        $secondInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-200',
        ]);

        $ascResponse = $this->getJson(route('billing.invoices.index', [
            'sort_by' => 'invoice_number',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $firstInvoice->id);
        $ascResponse->assertJsonPath('data.1.id', $secondInvoice->id);

        $descResponse = $this->getJson(route('billing.invoices.index', [
            'sort_by' => 'invoice_number',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $secondInvoice->id);
        $descResponse->assertJsonPath('data.1.id', $firstInvoice->id);
    }

    public function test_update_replaces_items_and_recalculates_totals_for_draft_invoice(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'balance_amount' => 100,
        ]);

        InvoiceItem::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'description' => 'Old item',
            'line_total' => 100,
        ]);

        $response = $this->putJson(route('billing.invoices.update', ['invoiceId' => $invoice->id]), [
            'items' => [
                [
                    'description' => 'Procedure',
                    'quantity' => 1,
                    'unit_price' => 300,
                    'discount_amount' => 20,
                    'tax_amount' => 10,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.total_amount', 290);
        $response->assertJsonPath('data.balance_amount', 290);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'subtotal_amount' => 300.00,
            'discount_amount' => 20.00,
            'tax_amount' => 10.00,
            'total_amount' => 290.00,
            'balance_amount' => 290.00,
        ]);

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Old item',
            'deleted_at' => null,
        ]);
    }

    public function test_destroy_deletes_only_draft_invoice_without_payments(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $draftInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->deleteJson(route('billing.invoices.destroy', ['invoiceId' => $draftInvoice->id]))
            ->assertNoContent();

        $this->assertSoftDeleted($draftInvoice);

        $issuedInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $response = $this->deleteJson(route('billing.invoices.destroy', ['invoiceId' => $issuedInvoice->id]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_bulk_destroy_deletes_only_valid_draft_invoices(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $draftInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $issuedInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $response = $this->deleteJson(route('billing.invoices.bulk-destroy'), [
            'ids' => [$draftInvoice->id, $issuedInvoice->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($draftInvoice);
        $this->assertDatabaseHas('invoices', ['id' => $issuedInvoice->id]);
    }

    public function test_show_returns_404_for_invoice_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);
        $invoice = Invoice::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
        ]);

        $response = $this->getJson(route('billing.invoices.show', ['invoiceId' => $invoice->id]));

        $response->assertNotFound();
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
