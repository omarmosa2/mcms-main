<?php

namespace Tests\Feature\Expenses;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_expense(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $category = ExpenseCategory::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->postJson(route('expenses.store'), [
            'category_id' => $category->id,
            'description' => 'Office supplies purchase',
            'amount' => 150.50,
            'expense_date' => now()->toDateString(),
            'notes' => 'Monthly office supplies',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 150.50);

        $expenseId = (int) $response->json('data.id');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'description' => 'Office supplies purchase',
            'amount' => 150.50,
            'status' => Expense::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'expenses.create',
        ]);
    }

    public function test_show_expense(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $expense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
            'description' => 'Utility bill',
            'amount' => 200,
        ]);

        $response = $this->getJson(route('expenses.show', ['expenseId' => $expense->id]));

        $response->assertOk();
        $response->assertJsonPath('data.id', $expense->id);
        $response->assertJsonPath('data.description', 'Utility bill');
        $response->assertJsonPath('data.amount', 200);
    }

    public function test_update_expense(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $category = ExpenseCategory::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $expense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
            'description' => 'Old description',
            'amount' => 100,
        ]);

        $response = $this->putJson(route('expenses.update', ['expenseId' => $expense->id]), [
            'category_id' => $category->id,
            'description' => 'Updated description',
            'amount' => 250.75,
            'expense_date' => now()->toDateString(),
            'notes' => 'Corrected amount',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.description', 'Updated description');
        $response->assertJsonPath('data.amount', 250.75);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => 'Updated description',
            'amount' => 250.75,
            'notes' => 'Corrected amount',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'expenses.update',
        ]);
    }

    public function test_approve_expense(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $expense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->postJson(route('expenses.approve', ['expenseId' => $expense->id]));

        $response->assertOk();
        $response->assertJsonPath('data.status', Expense::STATUS_APPROVED);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'expenses.approve',
        ]);
    }

    public function test_reject_expense(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $expense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->postJson(route('expenses.reject', ['expenseId' => $expense->id]));

        $response->assertOk();
        $response->assertJsonPath('data.status', Expense::STATUS_REJECTED);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_REJECTED,
            'approved_by' => $user->id,
        ]);
    }

    public function test_delete_expense(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $expense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->deleteJson(route('expenses.destroy', ['expenseId' => $expense->id]));

        $response->assertNoContent();

        $this->assertSoftDeleted($expense);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'expenses.delete',
        ]);
    }

    public function test_bulk_destroy_expenses(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $deletableExpense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $otherClinic = Clinic::factory()->create();
        $foreignExpense = Expense::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        $response = $this->deleteJson(route('expenses.bulk-destroy'), [
            'ids' => [$deletableExpense->id, $foreignExpense->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($deletableExpense);
        $this->assertDatabaseHas('expenses', ['id' => $foreignExpense->id]);
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
