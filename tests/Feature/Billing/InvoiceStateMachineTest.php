<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\TransitionInvoiceStatusAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private TransitionInvoiceStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(TransitionInvoiceStatusAction::class);
    }

    public function test_draft_can_transition_to_issued(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_ISSUED,
            context: ['balance_amount' => 100],
        );

        $this->assertEquals(Invoice::STATUS_ISSUED, $result->status);
        $this->assertNotNull($result->issued_at);
        $this->assertEquals($user->id, $result->issued_by);
    }

    public function test_issued_can_transition_to_partially_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_PARTIALLY_PAID,
            context: ['balance_amount' => 50],
        );

        $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $result->status);
        $this->assertEquals(50, (float) $result->balance_amount);
    }

    public function test_issued_can_transition_to_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_PAID,
            context: ['balance_amount' => 0],
        );

        $this->assertEquals(Invoice::STATUS_PAID, $result->status);
        $this->assertEquals(0, (float) $result->balance_amount);
    }

    public function test_partially_paid_can_transition_to_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'total_amount' => 100,
            'paid_amount' => 50,
            'balance_amount' => 50,
        ]);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_PAID,
            context: ['balance_amount' => 0],
        );

        $this->assertEquals(Invoice::STATUS_PAID, $result->status);
    }

    public function test_partially_paid_can_transition_back_to_issued(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'total_amount' => 100,
            'paid_amount' => 50,
            'balance_amount' => 50,
        ]);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_ISSUED,
            context: ['balance_amount' => 100],
        );

        $this->assertEquals(Invoice::STATUS_ISSUED, $result->status);
    }

    public function test_paid_can_transition_to_partially_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 100,
            'paid_amount' => 100,
            'balance_amount' => 0,
        ]);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_PARTIALLY_PAID,
            context: ['balance_amount' => 30],
        );

        $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $result->status);
        $this->assertEquals(30, (float) $result->balance_amount);
    }

    public function test_any_non_void_status_can_transition_to_void(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $statuses = [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ];

        foreach ($statuses as $status) {
            $invoice = Invoice::factory()->create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'status' => $status,
                'total_amount' => 100,
                'paid_amount' => 0,
                'balance_amount' => 100,
            ]);

            $result = $this->action->handle(
                clinicId: $clinic->id,
                invoiceId: $invoice->id,
                userId: $user->id,
                newStatus: Invoice::STATUS_VOID,
            );

            $this->assertEquals(Invoice::STATUS_VOID, $result->status, "Failed for status: {$status}");
            $this->assertNotNull($result->voided_at, "voided_at not set for status: {$status}");
            $this->assertEquals($user->id, $result->voided_by, "voided_by not set for status: {$status}");
        }
    }

    public function test_void_is_terminal_cannot_transition_from_void(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_VOID,
            'total_amount' => 100,
        ]);

        $targetStatuses = [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    invoiceId: $invoice->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for void -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid invoice status transition', $e->getMessage());
            }
        }
    }

    public function test_draft_cannot_transition_directly_to_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 100,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid invoice status transition');

        $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_PAID,
        );
    }

    public function test_draft_cannot_transition_to_partially_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 100,
        ]);

        $this->expectException(ValidationException::class);

        $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_PARTIALLY_PAID,
        );
    }

    public function test_paid_cannot_transition_to_issued_directly(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 100,
            'paid_amount' => 100,
            'balance_amount' => 0,
        ]);

        $this->expectException(ValidationException::class);

        $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_ISSUED,
        );
    }

    public function test_transition_logs_audit_entry(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 100,
        ]);

        $this->action->handle(
            clinicId: $clinic->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_ISSUED,
            context: ['balance_amount' => 100],
        );

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'billing.invoices.transition_status',
            'auditable_id' => $invoice->id,
            'auditable_type' => Invoice::class,
        ]);
    }

    public function test_transition_respects_clinic_isolation(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinicA->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinicA->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 100,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->action->handle(
            clinicId: $clinicB->id,
            invoiceId: $invoice->id,
            userId: $user->id,
            newStatus: Invoice::STATUS_ISSUED,
        );
    }

    public function test_terminal_statuses_constant_is_correct(): void
    {
        $this->assertEquals(
            [Invoice::STATUS_PAID, Invoice::STATUS_VOID],
            Invoice::TERMINAL_STATUSES,
        );
    }

    public function test_allowed_transitions_constant_matches_prd(): void
    {
        $expected = [
            Invoice::STATUS_DRAFT => [Invoice::STATUS_ISSUED, Invoice::STATUS_VOID],
            Invoice::STATUS_ISSUED => [Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID, Invoice::STATUS_VOID],
            Invoice::STATUS_PARTIALLY_PAID => [Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID, Invoice::STATUS_ISSUED, Invoice::STATUS_VOID],
            Invoice::STATUS_PAID => [Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_VOID],
            Invoice::STATUS_VOID => [],
        ];

        $this->assertEquals($expected, Invoice::ALLOWED_TRANSITIONS);
    }
}
