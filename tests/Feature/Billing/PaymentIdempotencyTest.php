<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\RecordPaymentAction;
use App\Actions\Billing\RefundPaymentAction;
use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_payment_with_same_idempotency_key_returns_same_payment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $idempotencyKey = 'test-payment-'.uniqid();

        $payment1 = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 100,
            'method' => 'cash',
            'idempotency_key' => $idempotencyKey,
        ]);

        $payment2 = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 100,
            'method' => 'cash',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertEquals($payment1->id, $payment2->id, 'Duplicate request should return same payment');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_different_idempotency_keys_create_separate_payments(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment1 = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 100,
            'method' => 'cash',
            'idempotency_key' => 'payment-key-1',
        ]);

        $payment2 = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 100,
            'method' => 'cash',
            'idempotency_key' => 'payment-key-2',
        ]);

        $this->assertNotEquals($payment1->id, $payment2->id, 'Different idempotency keys should create separate payments');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_payment_without_idempotency_key_generates_auto_key(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 100,
            'method' => 'cash',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 100,
        ]);
    }

    public function test_idempotency_record_is_created_for_payment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $idempotencyKey = 'test-idempotency-'.uniqid();

        app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 100,
            'method' => 'cash',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertDatabaseHas('idempotency_records', [
            'idempotency_key' => $idempotencyKey,
            'status' => 'completed',
        ]);
    }

    public function test_duplicate_refund_with_same_idempotency_key_returns_same_refund(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 200,
            'method' => 'cash',
        ]);

        $idempotencyKey = 'test-refund-'.uniqid();

        $refund1 = app(RefundPaymentAction::class)->handle($clinic->id, $payment->id, $user->id, [
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $refund2 = app(RefundPaymentAction::class)->handle($clinic->id, $payment->id, $user->id, [
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertEquals($refund1->id, $refund2->id, 'Duplicate refund should return same payment');
        $this->assertEquals(100, (float) $refund1->refund_amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_different_refund_idempotency_keys_create_separate_refunds(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 300,
            'method' => 'cash',
        ]);

        $refund1 = app(RefundPaymentAction::class)->handle($clinic->id, $payment->id, $user->id, [
            'amount' => 50,
            'idempotency_key' => 'refund-key-1',
        ]);

        $refund2 = app(RefundPaymentAction::class)->handle($clinic->id, $payment->id, $user->id, [
            'amount' => 50,
            'idempotency_key' => 'refund-key-2',
        ]);

        $this->assertDatabaseHas('idempotency_records', ['idempotency_key' => 'refund-key-1', 'status' => 'completed']);
        $this->assertDatabaseHas('idempotency_records', ['idempotency_key' => 'refund-key-2', 'status' => 'completed']);
        $this->assertEquals(100, (float) Payment::find($payment->id)->refund_amount);
    }

    public function test_refund_without_idempotency_key_generates_auto_key(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 200,
            'method' => 'cash',
        ]);

        $refund = app(RefundPaymentAction::class)->handle($clinic->id, $payment->id, $user->id, [
            'amount' => 100,
        ]);

        $this->assertEquals(100, (float) $refund->refund_amount);
        $this->assertDatabaseHas('idempotency_records', [
            'status' => 'completed',
            'operation_type' => 'payment.refund',
        ]);
    }

    public function test_refund_idempotency_record_is_created(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment = app(RecordPaymentAction::class)->handle($clinic->id, $invoice->id, $user->id, [
            'amount' => 200,
            'method' => 'cash',
        ]);

        $idempotencyKey = 'test-refund-idempotency-'.uniqid();

        app(RefundPaymentAction::class)->handle($clinic->id, $payment->id, $user->id, [
            'amount' => 100,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertDatabaseHas('idempotency_records', [
            'idempotency_key' => $idempotencyKey,
            'status' => 'completed',
            'clinic_id' => $clinic->id,
            'operation_type' => 'payment.refund',
        ]);
    }

    private function createAuthenticatedUser(Clinic $clinic): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($user, 'accountant');
        $this->actingAs($user);

        return $user;
    }
}
