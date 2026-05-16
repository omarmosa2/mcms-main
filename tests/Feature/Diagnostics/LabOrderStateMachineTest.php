<?php

namespace Tests\Feature\Diagnostics;

use App\Actions\Diagnostics\TransitionLabOrderStatusAction;
use App\Models\Clinic;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LabOrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private TransitionLabOrderStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(TransitionLabOrderStatusAction::class);
    }

    public function test_ordered_can_transition_to_sample_collected(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_ORDERED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_SAMPLE_COLLECTED,
        );

        $this->assertEquals(LabOrder::STATUS_SAMPLE_COLLECTED, $result->status);
        $this->assertNotNull($result->sample_collected_at);
    }

    public function test_ordered_can_transition_directly_to_resulted(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_ORDERED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_RESULTED,
        );

        $this->assertEquals(LabOrder::STATUS_RESULTED, $result->status);
        $this->assertNotNull($result->resulted_at);
    }

    public function test_ordered_can_transition_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_ORDERED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_CANCELED,
            context: ['cancel_reason' => 'Patient refused'],
        );

        $this->assertEquals(LabOrder::STATUS_CANCELED, $result->status);
        $this->assertNotNull($result->canceled_at);
        $this->assertEquals('Patient refused', $result->cancel_reason);
    }

    public function test_sample_collected_can_transition_to_resulted(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_SAMPLE_COLLECTED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_RESULTED,
        );

        $this->assertEquals(LabOrder::STATUS_RESULTED, $result->status);
        $this->assertNotNull($result->resulted_at);
    }

    public function test_sample_collected_can_transition_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_SAMPLE_COLLECTED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_CANCELED,
        );

        $this->assertEquals(LabOrder::STATUS_CANCELED, $result->status);
    }

    public function test_resulted_is_terminal_cannot_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_RESULTED);

        $targetStatuses = [
            LabOrder::STATUS_ORDERED,
            LabOrder::STATUS_SAMPLE_COLLECTED,
            LabOrder::STATUS_CANCELED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    labOrderId: $order->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for resulted -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid lab order status transition', $e->getMessage());
            }
        }
    }

    public function test_canceled_is_terminal_cannot_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_CANCELED);

        $targetStatuses = [
            LabOrder::STATUS_ORDERED,
            LabOrder::STATUS_SAMPLE_COLLECTED,
            LabOrder::STATUS_RESULTED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    labOrderId: $order->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for canceled -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid lab order status transition', $e->getMessage());
            }
        }
    }

    public function test_cleared_cancel_reason_when_not_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_ORDERED);

        $order->cancel_reason = 'Some reason';
        $order->save();

        $result = $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_RESULTED,
        );

        $this->assertNull($result->cancel_reason);
    }

    public function test_transition_logs_audit_entry(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createLabOrder($clinic, LabOrder::STATUS_ORDERED);

        $this->action->handle(
            clinicId: $clinic->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_RESULTED,
        );

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'diagnostics.lab_orders.transition_status',
            'auditable_id' => $order->id,
            'auditable_type' => LabOrder::class,
        ]);
    }

    public function test_transition_respects_clinic_isolation(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $order = $this->createLabOrder($clinicA, LabOrder::STATUS_ORDERED);

        $this->expectException(ModelNotFoundException::class);

        $this->action->handle(
            clinicId: $clinicB->id,
            labOrderId: $order->id,
            userId: $user->id,
            newStatus: LabOrder::STATUS_RESULTED,
        );
    }

    public function test_terminal_statuses_constant_is_correct(): void
    {
        $this->assertEquals(
            [LabOrder::STATUS_RESULTED, LabOrder::STATUS_CANCELED],
            LabOrder::TERMINAL_STATUSES,
        );
    }

    public function test_allowed_transitions_constant_matches_prd(): void
    {
        $expected = [
            LabOrder::STATUS_ORDERED => [LabOrder::STATUS_SAMPLE_COLLECTED, LabOrder::STATUS_RESULTED, LabOrder::STATUS_CANCELED],
            LabOrder::STATUS_SAMPLE_COLLECTED => [LabOrder::STATUS_RESULTED, LabOrder::STATUS_CANCELED],
            LabOrder::STATUS_RESULTED => [],
            LabOrder::STATUS_CANCELED => [],
        ];

        $this->assertEquals($expected, LabOrder::ALLOWED_TRANSITIONS);
    }

    private function createLabOrder(Clinic $clinic, string $status): LabOrder
    {
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);

        return LabOrder::query()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'ordered_by' => $user->id,
            'test_name' => 'Complete Blood Count',
            'test_code' => 'CBC-001',
            'status' => $status,
        ]);
    }
}
