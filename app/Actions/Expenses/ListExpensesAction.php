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
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?int $categoryId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = Expense::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with(['category', 'user', 'approver']);

        if ($search !== null) {
            $query->where('description', 'like', '%'.trim($search).'%');
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($dateFrom !== null) {
            $query->where('expense_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('expense_date', '<=', $dateTo);
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $expenses = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'expenses.index',
            metadata: [
                'per_page' => $perPage,
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId,
                'sort_by' => $sortBy,
                'returned' => $expenses->count(),
            ],
        );

        return $expenses;
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
