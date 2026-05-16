<?php

namespace Tests\Feature\Pharmacy;

use App\Actions\Pharmacy\TransitionPrescriptionStatusAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PrescriptionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private TransitionPrescriptionStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(TransitionPrescriptionStatusAction::class);
    }

    public function test_draft_can_transition_to_issued(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_DRAFT);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_ISSUED,
        );

        $this->assertEquals(Prescription::STATUS_ISSUED, $result->status);
        $this->assertNotNull($result->issued_at);
    }

    public function test_draft_can_transition_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_DRAFT);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_CANCELED,
            context: ['cancel_reason' => 'Doctor changed mind'],
        );

        $this->assertEquals(Prescription::STATUS_CANCELED, $result->status);
        $this->assertNotNull($result->canceled_at);
        $this->assertEquals('Doctor changed mind', $result->cancel_reason);
    }

    public function test_issued_can_transition_to_dispensed(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_ISSUED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_DISPENSED,
        );

        $this->assertEquals(Prescription::STATUS_DISPENSED, $result->status);
        $this->assertNotNull($result->dispensed_at);
    }

    public function test_issued_can_transition_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_ISSUED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_CANCELED,
        );

        $this->assertEquals(Prescription::STATUS_CANCELED, $result->status);
    }

    public function test_dispensed_is_terminal_cannot_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_DISPENSED);

        $targetStatuses = [
            Prescription::STATUS_DRAFT,
            Prescription::STATUS_ISSUED,
            Prescription::STATUS_CANCELED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    prescriptionId: $prescription->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for dispensed -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid prescription status transition', $e->getMessage());
            }
        }
    }

    public function test_canceled_is_terminal_cannot_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_CANCELED);

        $targetStatuses = [
            Prescription::STATUS_DRAFT,
            Prescription::STATUS_ISSUED,
            Prescription::STATUS_DISPENSED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    prescriptionId: $prescription->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for canceled -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid prescription status transition', $e->getMessage());
            }
        }
    }

    public function test_cleared_cancel_reason_when_not_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_DRAFT);

        $prescription->cancel_reason = 'Some reason';
        $prescription->save();

        $result = $this->action->handle(
            clinicId: $clinic->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_ISSUED,
        );

        $this->assertNull($result->cancel_reason);
    }

    public function test_transition_logs_audit_entry(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $prescription = $this->createPrescription($clinic, Prescription::STATUS_DRAFT);

        $this->action->handle(
            clinicId: $clinic->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_ISSUED,
        );

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'pharmacy.prescriptions.transition_status',
            'auditable_id' => $prescription->id,
            'auditable_type' => Prescription::class,
        ]);
    }

    public function test_transition_respects_clinic_isolation(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $prescription = $this->createPrescription($clinicA, Prescription::STATUS_DRAFT);

        $this->expectException(ModelNotFoundException::class);

        $this->action->handle(
            clinicId: $clinicB->id,
            prescriptionId: $prescription->id,
            userId: $user->id,
            newStatus: Prescription::STATUS_ISSUED,
        );
    }

    public function test_terminal_statuses_constant_is_correct(): void
    {
        $this->assertEquals(
            [Prescription::STATUS_DISPENSED, Prescription::STATUS_CANCELED],
            Prescription::TERMINAL_STATUSES,
        );
    }

    public function test_allowed_transitions_constant_matches_prd(): void
    {
        $expected = [
            Prescription::STATUS_DRAFT => [Prescription::STATUS_ISSUED, Prescription::STATUS_CANCELED],
            Prescription::STATUS_ISSUED => [Prescription::STATUS_DISPENSED, Prescription::STATUS_CANCELED],
            Prescription::STATUS_DISPENSED => [],
            Prescription::STATUS_CANCELED => [],
        ];

        $this->assertEquals($expected, Prescription::ALLOWED_TRANSITIONS);
    }

    private function createPrescription(Clinic $clinic, string $status): Prescription
    {
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
        ]);

        return Prescription::query()->create([
            'clinic_id' => $clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'prescribed_by' => $user->id,
            'prescription_number' => 'RX-TEST-001',
            'status' => $status,
        ]);
    }
}
