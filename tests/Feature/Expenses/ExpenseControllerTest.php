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
            'title' => 'Office supplies purchase',
            'description' => 'Monthly office supplies',
            'amount' => 150.50,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'pending',
            'category_id' => $category->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 150.50);

        $expenseId = (int) $response->json('data.id');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Office supplies purchase',
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
            'title' => 'Utility bill',
            'amount' => 200,
        ]);

        $response = $this->getJson(route('expenses.show', ['expenseId' => $expense->id]));

        $response->assertOk();
        $response->assertJsonPath('data.id', $expense->id);
        $response->assertJsonPath('data.title', 'Utility bill');
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
            'title' => 'Old title',
            'amount' => 100,
        ]);

        $response = $this->putJson(route('expenses.update', ['expenseId' => $expense->id]), [
            'title' => 'Updated title',
            'description' => 'Corrected amount',
            'amount' => 250.75,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'paid',
            'category_id' => $category->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated title');
        $response->assertJsonPath('data.amount', 250.75);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'title' => 'Updated title',
            'amount' => 250.75,
            'status' => Expense::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'expenses.update',
        ]);
    }

    public function test_update_expense_status(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');

        $expense = Expense::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->postJson(route('expenses.update-status', ['expenseId' => $expense->id]), [
            'status' => Expense::STATUS_PAID,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', Expense::STATUS_PAID);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_PAID,
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

    public function test_store_expense_with_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->postJson(route('expenses.store'), [
            'title' => 'Clinic-specific expense',
            'amount' => 500,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'card',
            'status' => 'paid',
            'clinic_id' => $clinic->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('expenses', [
            'title' => 'Clinic-specific expense',
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_store_expense_general(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $response = $this->postJson(route('expenses.store'), [
            'title' => 'General expense',
            'amount' => 300,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $response->assertCreated();
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
