<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\TransitionPaymentStatusAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private TransitionPaymentStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(TransitionPaymentStatusAction::class);
    }

    public function test_recorded_can_transition_to_refunded(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_RECORDED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: Payment::STATUS_REFUNDED,
            context: ['refund_amount' => 50],
        );

        $this->assertEquals(Payment::STATUS_REFUNDED, $result->status);
        $this->assertNotNull($result->refunded_at);
        $this->assertEquals(50, (float) $result->refund_amount);
    }

    public function test_recorded_can_transition_to_voided(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_RECORDED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: Payment::STATUS_VOIDED,
        );

        $this->assertEquals(Payment::STATUS_VOIDED, $result->status);
        $this->assertNotNull($result->voided_at);
        $this->assertEquals($user->id, $result->voided_by);
    }

    public function test_refunded_can_transition_to_refunded_again(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_REFUNDED, 100, 50);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: Payment::STATUS_REFUNDED,
            context: ['refund_amount' => 75],
        );

        $this->assertEquals(Payment::STATUS_REFUNDED, $result->status);
        $this->assertEquals(75, (float) $result->refund_amount);
    }

    public function test_refunded_can_transition_to_voided(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_REFUNDED);

        $result = $this->action->handle(
            clinicId: $clinic->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: Payment::STATUS_VOIDED,
        );

        $this->assertEquals(Payment::STATUS_VOIDED, $result->status);
        $this->assertNotNull($result->voided_at);
    }

    public function test_voided_is_terminal_cannot_transition_from_voided(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_VOIDED);

        $targetStatuses = [
            Payment::STATUS_RECORDED,
            Payment::STATUS_REFUNDED,
        ];

        foreach ($targetStatuses as $targetStatus) {
            try {
                $this->action->handle(
                    clinicId: $clinic->id,
                    paymentId: $payment->id,
                    userId: $user->id,
                    newStatus: $targetStatus,
                );

                $this->fail("Expected ValidationException for voided -> {$targetStatus}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid payment status transition', $e->getMessage());
            }
        }
    }

    public function test_recorded_cannot_transition_to_invalid_status(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_RECORDED);

        $this->expectException(ValidationException::class);

        $this->action->handle(
            clinicId: $clinic->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: 'invalid_status',
        );
    }

    public function test_transition_logs_audit_entry(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $payment = $this->createPayment($clinic, Payment::STATUS_RECORDED);

        $this->action->handle(
            clinicId: $clinic->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: Payment::STATUS_REFUNDED,
            context: ['refund_amount' => 50],
        );

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'billing.payments.transition_status',
            'auditable_id' => $payment->id,
            'auditable_type' => Payment::class,
        ]);
    }

    public function test_transition_respects_clinic_isolation(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $payment = $this->createPayment($clinicA, Payment::STATUS_RECORDED);

        $this->expectException(ModelNotFoundException::class);

        $this->action->handle(
            clinicId: $clinicB->id,
            paymentId: $payment->id,
            userId: $user->id,
            newStatus: Payment::STATUS_REFUNDED,
        );
    }

    public function test_terminal_statuses_constant_is_correct(): void
    {
        $this->assertEquals(
            [Payment::STATUS_VOIDED],
            Payment::TERMINAL_STATUSES,
        );
    }

    public function test_allowed_transitions_constant_matches_prd(): void
    {
        $expected = [
            Payment::STATUS_RECORDED => [Payment::STATUS_REFUNDED, Payment::STATUS_VOIDED],
            Payment::STATUS_REFUNDED => [Payment::STATUS_REFUNDED, Payment::STATUS_VOIDED],
            Payment::STATUS_VOIDED => [],
        ];

        $this->assertEquals($expected, Payment::ALLOWED_TRANSITIONS);
    }

    private function createPayment(Clinic $clinic, string $status, float $amount = 100, float $refundAmount = 0): Payment
    {
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100,
            'paid_amount' => $amount,
            'balance_amount' => 0,
        ]);

        return Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'status' => $status,
            'amount' => $amount,
            'refund_amount' => $refundAmount,
        ]);
    }
}
