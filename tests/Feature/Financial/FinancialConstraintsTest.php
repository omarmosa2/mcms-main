<?php

namespace Tests\Feature\Financial;

use App\Models\Clinic;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_rejects_negative_subtotal(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->make([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'subtotal_amount' => -10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice subtotal_amount cannot be negative');

        $invoice->save();
    }

    public function test_invoice_rejects_negative_discount(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->make([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'subtotal_amount' => 100,
            'discount_amount' => -5,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice discount_amount cannot be negative');

        $invoice->save();
    }

    public function test_invoice_rejects_negative_tax(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->make([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'subtotal_amount' => 100,
            'tax_amount' => -10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice tax_amount cannot be negative');

        $invoice->save();
    }

    public function test_invoice_rejects_discount_exceeding_subtotal(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $invoice = Invoice::factory()->make([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'subtotal_amount' => 50,
            'discount_amount' => 100,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice discount_amount cannot exceed subtotal_amount');

        $invoice->save();
    }

    public function test_invoice_item_rejects_non_positive_quantity(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->make([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice item quantity must be positive');

        $item->save();
    }

    public function test_invoice_item_rejects_negative_unit_price(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->make([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => -10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice item unit_price cannot be negative');

        $item->save();
    }

    public function test_invoice_item_rejects_negative_discount(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->make([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => -5,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice item discount_amount cannot be negative');

        $item->save();
    }

    public function test_invoice_item_rejects_negative_tax(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->make([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 100,
            'tax_amount' => -10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice item tax_amount cannot be negative');

        $item->save();
    }

    public function test_invoice_item_rejects_negative_line_total(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->make([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => -10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice item line_total cannot be negative');

        $item->save();
    }

    public function test_invoice_item_rejects_discount_exceeding_line_value(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->make([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 2,
            'unit_price' => 50,
            'discount_amount' => 150,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice item discount_amount cannot exceed quantity multiplied by unit_price');

        $item->save();
    }

    public function test_payment_plan_rejects_non_positive_installment_count(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);

        $plan = PaymentPlan::factory()->make([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'installment_count' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment plan installment_count must be positive');

        $plan->save();
    }

    public function test_payment_plan_rejects_negative_min_amount(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);

        $plan = PaymentPlan::factory()->make([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'installment_count' => 3,
            'min_amount' => -100,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment plan min_amount cannot be negative');

        $plan->save();
    }

    public function test_installment_rejects_non_positive_number(): void
    {
        $clinic = Clinic::factory()->create();
        $plan = PaymentPlan::factory()->create(['clinic_id' => $clinic->id]);

        $installment = Installment::factory()->make([
            'clinic_id' => $clinic->id,
            'payment_plan_id' => $plan->id,
            'installment_number' => 0,
            'amount' => 100,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Installment number must be positive');

        $installment->save();
    }

    public function test_installment_rejects_non_positive_amount(): void
    {
        $clinic = Clinic::factory()->create();
        $plan = PaymentPlan::factory()->create(['clinic_id' => $clinic->id]);

        $installment = Installment::factory()->make([
            'clinic_id' => $clinic->id,
            'payment_plan_id' => $plan->id,
            'installment_number' => 1,
            'amount' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Installment amount must be positive');

        $installment->save();
    }

    public function test_installment_rejects_negative_paid_amount(): void
    {
        $clinic = Clinic::factory()->create();
        $plan = PaymentPlan::factory()->create(['clinic_id' => $clinic->id]);

        $installment = Installment::factory()->make([
            'clinic_id' => $clinic->id,
            'payment_plan_id' => $plan->id,
            'installment_number' => 1,
            'amount' => 100,
            'paid_amount' => -10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Installment paid_amount cannot be negative');

        $installment->save();
    }

    public function test_installment_rejects_paid_amount_exceeding_amount(): void
    {
        $clinic = Clinic::factory()->create();
        $plan = PaymentPlan::factory()->create(['clinic_id' => $clinic->id]);

        $installment = Installment::factory()->make([
            'clinic_id' => $clinic->id,
            'payment_plan_id' => $plan->id,
            'installment_number' => 1,
            'amount' => 100,
            'paid_amount' => 150,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Installment paid_amount cannot exceed amount');

        $installment->save();
    }

    public function test_valid_invoice_item_passes_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $invoice = Invoice::factory()->create(['clinic_id' => $clinic->id]);

        $item = InvoiceItem::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $invoice->id,
            'quantity' => 3,
            'unit_price' => 50,
            'discount_amount' => 30,
            'tax_amount' => 10,
            'line_total' => 130,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_valid_installment_passes_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $plan = PaymentPlan::factory()->create(['clinic_id' => $clinic->id]);

        $installment = Installment::factory()->create([
            'clinic_id' => $clinic->id,
            'payment_plan_id' => $plan->id,
            'installment_number' => 1,
            'amount' => 200,
            'paid_amount' => 100,
        ]);

        $this->assertDatabaseHas('installments', [
            'id' => $installment->id,
            'amount' => 200,
        ]);
    }

    public function test_valid_payment_plan_passes_validation(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);

        $plan = PaymentPlan::factory()->create([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'installment_count' => 6,
            'min_amount' => 500,
        ]);

        $this->assertDatabaseHas('payment_plans', [
            'id' => $plan->id,
            'installment_count' => 6,
        ]);
    }

    public function test_constraints_exist_for_mysql(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('CHECK constraints are not enforced in SQLite.');
        }

        $constraints = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_TYPE = 'CHECK'
            AND TABLE_NAME IN ('invoices', 'invoice_items', 'payments', 'payment_plans', 'installments')
        ");

        $constraintNames = array_map(fn ($c) => $c->CONSTRAINT_NAME, $constraints);

        $expectedConstraints = [
            'chk_positive_totals',
            'chk_paid_not_negative',
            'chk_balance_not_negative',
            'chk_paid_not_exceed_total',
            'chk_balance_not_exceed_total',
            'chk_invoice_subtotal_non_negative',
            'chk_invoice_discount_non_negative',
            'chk_invoice_tax_non_negative',
            'chk_invoice_discount_within_subtotal',
            'chk_payment_amount_positive',
            'chk_refund_not_exceed',
            'chk_refund_not_negative',
            'chk_item_quantity_positive',
            'chk_item_unit_price_non_negative',
            'chk_item_discount_non_negative',
            'chk_item_tax_non_negative',
            'chk_item_line_total_non_negative',
            'chk_item_discount_within_line',
            'chk_plan_installment_count_positive',
            'chk_plan_min_amount_non_negative',
            'chk_installment_number_positive',
            'chk_installment_amount_positive',
            'chk_installment_paid_non_negative',
            'chk_installment_paid_within_amount',
        ];

        foreach ($expectedConstraints as $constraint) {
            $this->assertContains($constraint, $constraintNames, "Missing constraint: {$constraint}");
        }
    }
}
