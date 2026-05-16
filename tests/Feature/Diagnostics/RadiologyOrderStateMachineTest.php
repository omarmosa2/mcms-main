<?php

namespace Tests\Feature\Diagnostics;

use App\Actions\Diagnostics\TransitionRadiologyOrderStatusAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\RadiologyOrder;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RadiologyOrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private TransitionRadiologyOrderStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(TransitionRadiologyOrderStatusAction::class);
    }

    public function test_ordered_can_transition_to_completed(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_ORDERED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_COMPLETED,
        );

        $this->assertEquals(RadiologyOrder::STATUS_COMPLETED, $result->status);
        $this->assertNotNull($result->completed_at);
    }

    public function test_ordered_can_transition_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_ORDERED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_CANCELED,
            context: ['cancel_reason' => 'Patient refused'],
        );

        $this->assertEquals(RadiologyOrder::STATUS_CANCELED, $result->status);
        $this->assertNotNull($result->canceled_at);
        $this->assertEquals('Patient refused', $result->cancel_reason);
    }

    public function test_completed_can_transition_to_reported(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_COMPLETED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_REPORTED,
        );

        $this->assertEquals(RadiologyOrder::STATUS_REPORTED, $result->status);
        $this->assertNotNull($result->reported_at);
    }

    public function test_completed_can_transition_to_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_COMPLETED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_CANCELED,
        );

        $this->assertEquals(RadiologyOrder::STATUS_CANCELED, $result->status);
    }

    public function test_reported_is_terminal_cannot_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_REPORTED);

        $targetStatuses = [
            RadiologyOrder::STATUS_ORDERED,
            RadiologyOrder::STATUS_COMPLETED,
            RadiologyOrder::STATUS_CANCELED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    radiologyOrderId: $order->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for reported -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid radiology order status transition', $e->getMessage());
            }
        }
    }

    public function test_canceled_is_terminal_cannot_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_CANCELED);

        $targetStatuses = [
            RadiologyOrder::STATUS_ORDERED,
            RadiologyOrder::STATUS_COMPLETED,
            RadiologyOrder::STATUS_REPORTED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    radiologyOrderId: $order->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for canceled -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid radiology order status transition', $e->getMessage());
            }
        }
    }

    public function test_cleared_cancel_reason_when_not_canceled(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_ORDERED);

        $order->cancel_reason = 'Some reason';
        $order->save();

        $result = $this->action->handle(
            clinicId: $clinic->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_COMPLETED,
        );

        $this->assertNull($result->cancel_reason);
    }

    public function test_transition_logs_audit_entry(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $order = $this->createRadiologyOrder($clinic, RadiologyOrder::STATUS_ORDERED);

        $this->action->handle(
            clinicId: $clinic->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_COMPLETED,
        );

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'diagnostics.radiology_orders.transition_status',
            'auditable_id' => $order->id,
            'auditable_type' => RadiologyOrder::class,
        ]);
    }

    public function test_transition_respects_clinic_isolation(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $order = $this->createRadiologyOrder($clinicA, RadiologyOrder::STATUS_ORDERED);

        $this->expectException(ModelNotFoundException::class);

        $this->action->handle(
            clinicId: $clinicB->id,
            radiologyOrderId: $order->id,
            userId: $user->id,
            newStatus: RadiologyOrder::STATUS_COMPLETED,
        );
    }

    public function test_terminal_statuses_constant_is_correct(): void
    {
        $this->assertEquals(
            [RadiologyOrder::STATUS_REPORTED, RadiologyOrder::STATUS_CANCELED],
            RadiologyOrder::TERMINAL_STATUSES,
        );
    }

    public function test_allowed_transitions_constant_matches_prd(): void
    {
        $expected = [
            RadiologyOrder::STATUS_ORDERED => [RadiologyOrder::STATUS_COMPLETED, RadiologyOrder::STATUS_CANCELED],
            RadiologyOrder::STATUS_COMPLETED => [RadiologyOrder::STATUS_REPORTED, RadiologyOrder::STATUS_CANCELED],
            RadiologyOrder::STATUS_REPORTED => [],
            RadiologyOrder::STATUS_CANCELED => [],
        ];

        $this->assertEquals($expected, RadiologyOrder::ALLOWED_TRANSITIONS);
    }

    private function createRadiologyOrder(Clinic $clinic, string $status): RadiologyOrder
    {
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
        ]);

        return RadiologyOrder::query()->create([
            'clinic_id' => $clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'ordered_by' => $user->id,
            'study_name' => 'Chest X-Ray',
            'status' => $status,
        ]);
    }
}
