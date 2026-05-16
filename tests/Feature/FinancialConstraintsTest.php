<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_total_amount_cannot_be_negative_via_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice total_amount cannot be negative.');

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'total_amount' => -100,
        ]);
    }

    public function test_invoice_paid_amount_cannot_be_negative_via_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice paid_amount cannot be negative.');

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'total_amount' => 100,
            'paid_amount' => -50,
        ]);
    }

    public function test_invoice_paid_amount_cannot_exceed_total_via_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice paid_amount cannot exceed total_amount.');

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'total_amount' => 100,
            'paid_amount' => 200,
        ]);
    }

    public function test_invoice_balance_cannot_exceed_total_via_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice balance_amount cannot exceed total_amount.');

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 150,
        ]);
    }

    public function test_payment_amount_must_be_positive_via_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be positive.');

        Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'amount' => -50,
        ]);
    }

    public function test_refund_cannot_exceed_payment_amount_via_validation(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment refund_amount cannot exceed amount.');

        Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'amount' => 100,
            'refund_amount' => 150,
        ]);
    }

    public function test_valid_invoice_passes_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'total_amount' => 100,
            'paid_amount' => 50,
            'balance_amount' => 50,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total_amount' => 100,
            'paid_amount' => 50,
            'balance_amount' => 50,
        ]);
    }

    public function test_valid_payment_passes_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 500,
            'paid_amount' => 0,
            'balance_amount' => 500,
        ]);

        $payment = Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'received_by' => $user->id,
            'amount' => 100,
            'refund_amount' => 0,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 100,
            'refund_amount' => 0,
        ]);
    }
}
