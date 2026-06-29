<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListExpensesAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?int $categoryId = null,
        ?int $clinicId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $paymentMethod = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = Expense::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed()
            ->with(['category', 'user', 'creator', 'clinic']);

        if ($clinicId !== null) {
            $query->where(function (Builder $q) use ($clinicId) {
                $q->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id');
            });
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhere('paid_to', 'like', $searchTerm)
                    ->orWhere('reference_number', 'like', $searchTerm)
                    ->orWhere('expense_number', 'like', $searchTerm);
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($paymentMethod !== null) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($dateFrom !== null) {
            $query->where('expense_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('expense_date', '<=', $dateTo);
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $expenses = $query->paginate($perPage);

        if ($clinicId !== null && $clinicId > 0) {
            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'expenses.index',
                metadata: [
                    'per_page' => $perPage,
                    'search' => $search,
                    'status' => $status,
                    'category_id' => $categoryId,
                    'clinic_id' => $clinicId,
                    'sort_by' => $sortBy,
                    'returned' => $expenses->count(),
                ],
            );
        }

        return $expenses;
    }

    public function getStats(
        ?int $clinicId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $categoryId = null,
        ?string $paymentMethod = null,
    ): array {
        $query = Expense::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed();

        if ($clinicId !== null) {
            $query->where(function (Builder $q) use ($clinicId) {
                $q->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id');
            });
        }

        if ($dateFrom !== null) {
            $query->where('expense_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('expense_date', '<=', $dateTo);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($paymentMethod !== null) {
            $query->where('payment_method', $paymentMethod);
        }

        $totalExpenses = (float) $query->clone()->sum('amount');

        $currentMonthStart = now()->startOfMonth()->toDateString();
        $currentMonthEnd = now()->endOfMonth()->toDateString();
        $monthlyExpenses = (float) $query->clone()
            ->whereBetween('expense_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        $paidExpenses = (float) $query->clone()
            ->where('status', Expense::STATUS_PAID)
            ->sum('amount');

        $pendingExpenses = (float) $query->clone()
            ->where('status', Expense::STATUS_PENDING)
            ->sum('amount');

        $expensesCount = $query->clone()->count();

        $topCategory = $query->clone()
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->selectRaw('COALESCE(expense_categories.name, \'بدون تصنيف\') as category_name, SUM(expenses.amount) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->first();

        return [
            'total_expenses' => $totalExpenses,
            'monthly_expenses' => $monthlyExpenses,
            'paid_expenses' => $paidExpenses,
            'pending_expenses' => $pendingExpenses,
            'expenses_count' => $expensesCount,
            'top_category' => $topCategory ? [
                'name' => $topCategory->category_name,
                'total' => (float) $topCategory->total,
            ] : null,
        ];
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'amount') {
            $query->orderBy('amount', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'expense_date') {
            $query->orderBy('expense_date', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query->orderBy('status', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->orderBy('created_at', $direction)->orderBy('id', 'desc');
    }
}
