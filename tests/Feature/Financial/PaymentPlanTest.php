<?php

namespace Tests\Feature\Financial;

use App\Models\Clinic;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_payment_plan(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $this->actingAs($user);

        $response = $this->postJson(route('payment-plans.store'), [
            'name' => 'Monthly Plan',
            'description' => '12 monthly installments',
            'installment_count' => 12,
            'frequency' => 'monthly',
            'min_amount' => 50000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payment_plans', [
            'clinic_id' => $clinic->id,
            'name' => 'Monthly Plan',
            'installment_count' => 12,
            'frequency' => 'monthly',
            'min_amount' => 50000,
        ]);
    }

    public function test_can_list_payment_plans(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        PaymentPlan::factory()->for($clinic)->count(3)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('payment-plans.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_html_financial_subpages_redirect_to_single_financial_page(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $this->actingAs($user);

        $this->get(route('payment-plans.index'))
            ->assertRedirect(route('financial.index'));

        $this->get(route('installments.index'))
            ->assertRedirect(route('financial.index'));
    }

    public function test_can_apply_payment_plan_to_invoice(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $plan = PaymentPlan::factory()->for($clinic)->create([
            'installment_count' => 3,
            'frequency' => 'monthly',
            'min_amount' => 0,
        ]);

        $invoice = Invoice::factory()->for($clinic)->create([
            'total_amount' => 30000,
            'paid_amount' => 0,
            'balance_amount' => 30000,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('payment-plans.apply', $plan->id), [
            'invoice_id' => $invoice->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('installments', 3);
        $this->assertDatabaseHas('installments', [
            'payment_plan_id' => $plan->id,
            'invoice_id' => $invoice->id,
            'status' => 'pending',
        ]);
    }

    public function test_can_process_installment_payment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $plan = PaymentPlan::factory()->for($clinic)->create([
            'installment_count' => 3,
            'min_amount' => 0,
        ]);

        $invoice = Invoice::factory()->for($clinic)->create([
            'total_amount' => 30000,
            'paid_amount' => 0,
            'balance_amount' => 30000,
        ]);

        $installments = [];
        for ($i = 1; $i <= 3; $i++) {
            $installments[] = Installment::factory()->create([
                'clinic_id' => $clinic->id,
                'payment_plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
                'installment_number' => $i,
                'amount' => 10000,
                'due_date' => now()->addMonths($i),
                'status' => 'pending',
                'paid_amount' => 0,
            ]);
        }

        $this->actingAs($user);

        $response = $this->postJson(route('installments.pay', $installments[0]->id), [
            'amount' => 10000,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('installments', [
            'id' => $installments[0]->id,
            'status' => 'paid',
            'paid_amount' => 10000,
        ]);
    }

    public function test_installment_marks_as_paid_when_fully_paid(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $plan = PaymentPlan::factory()->for($clinic)->create(['min_amount' => 0]);

        $invoice = Invoice::factory()->for($clinic)->create([
            'total_amount' => 10000,
            'paid_amount' => 0,
            'balance_amount' => 10000,
        ]);

        $installment = Installment::factory()->create([
            'clinic_id' => $clinic->id,
            'payment_plan_id' => $plan->id,
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'status' => 'pending',
            'paid_amount' => 0,
        ]);

        $this->actingAs($user);

        $this->postJson(route('installments.pay', $installment->id), [
            'amount' => 10000,
        ]);

        $installment->refresh();
        $this->assertEquals('paid', $installment->status);
        $this->assertNotNull($installment->paid_at);
    }

    public function test_cannot_pay_already_paid_installment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();

        $installment = Installment::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => 'paid',
            'paid_amount' => 10000,
            'amount' => 10000,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('installments.pay', $installment->id), [
            'amount' => 5000,
        ]);

        $response->assertStatus(422);
    }

    public function test_installment_is_overdue_when_past_due_date(): void
    {
        $clinic = Clinic::factory()->create();

        $installment = Installment::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => 'pending',
            'due_date' => now()->subDays(5),
        ]);

        $this->assertTrue($installment->isOverdue());
    }

    public function test_installment_is_not_overdue_when_future_due_date(): void
    {
        $clinic = Clinic::factory()->create();

        $installment = Installment::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => 'pending',
            'due_date' => now()->addDays(5),
        ]);

        $this->assertFalse($installment->isOverdue());
    }

    public function test_remaining_amount_is_correct(): void
    {
        $clinic = Clinic::factory()->create();

        $installment = Installment::factory()->create([
            'clinic_id' => $clinic->id,
            'amount' => 10000,
            'paid_amount' => 3000,
        ]);

        $this->assertEquals(7000, $installment->remainingAmount());
    }

    public function test_payment_plans_are_scoped_to_clinic(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        PaymentPlan::factory()->for($clinic1)->create(['name' => 'Clinic 1 Plan']);
        PaymentPlan::factory()->for($clinic2)->create(['name' => 'Clinic 2 Plan']);

        $user = User::factory()->for($clinic1)->create();
        $this->actingAs($user);

        $response = $this->getJson(route('payment-plans.index'));

        $response->assertOk();
        $response->assertJsonPath('data.data.0.name', 'Clinic 1 Plan');
        $response->assertJsonMissingPath('data.data.1');
    }
}
