<?php

namespace Tests\Feature\Billing;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_records_payment_and_updates_invoice_balances(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'accountant');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 300,
            'paid_amount' => 0,
            'balance_amount' => 300,
        ]);

        $response = $this->postJson(route('billing.payments.store', ['invoiceId' => $invoice->id]), [
            'amount' => 100,
            'method' => 'cash',
            'payment_reference' => 'PAY-100',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', Payment::STATUS_RECORDED);
        $response->assertJsonPath('data.amount', 100);

        $paymentId = (int) $response->json('data.id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'status' => Payment::STATUS_RECORDED,
            'amount' => 100.00,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'paid_amount' => 100.00,
            'balance_amount' => 200.00,
        ]);
    }

    public function test_store_rejects_payment_for_draft_invoice(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'accountant');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 100,
            'balance_amount' => 100,
        ]);

        $response = $this->postJson(route('billing.payments.store', ['invoiceId' => $invoice->id]), [
            'amount' => 50,
            'method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_refund_updates_payment_and_invoice_totals(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'accountant');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 250,
            'paid_amount' => 250,
            'balance_amount' => 0,
        ]);

        $payment = Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'status' => Payment::STATUS_RECORDED,
            'amount' => 250,
            'refund_amount' => 0,
        ]);

        $response = $this->patchJson(route('billing.payments.refund', ['paymentId' => $payment->id]), [
            'amount' => 50,
            'notes' => 'Overpayment correction',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', Payment::STATUS_REFUNDED);
        $response->assertJsonPath('data.refund_amount', 50);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
            'refund_amount' => 50.00,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
            'paid_amount' => 200.00,
            'balance_amount' => 50.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'billing.payments.refund',
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_user_without_payment_permission_gets_forbidden(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);
        app(AssignUserRoleAction::class)->handle($user, 'receptionist');
        $this->actingAs($user);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100,
            'balance_amount' => 100,
        ]);

        $response = $this->postJson(route('billing.payments.store', ['invoiceId' => $invoice->id]), [
            'amount' => 10,
            'method' => 'cash',
        ]);

        $response->assertForbidden();
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
