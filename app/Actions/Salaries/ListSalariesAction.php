<?php

namespace App\Actions\Salaries;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Salary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalariesAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $periodMonth = null,
        string $sortBy = 'period_month',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = Salary::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with(['user:id,name,email']);

        if ($search !== null) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.trim($search).'%'));
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($periodMonth !== null) {
            $query->where('period_month', $periodMonth);
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $salaries = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'salaries.index',
            metadata: [
                'per_page' => $perPage,
                'search' => $search,
                'status' => $status,
            ],
        );

        return $salaries;
    }

    private function applySorting($query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'period_month') {
            $query->orderBy('period_month', $direction);
        } elseif ($sortBy === 'net_salary') {
            $query->orderBy('net_salary', $direction);
        } elseif ($sortBy === 'status') {
            $query->orderBy('status', $direction);
        } else {
            $query->orderBy('period_month', 'desc');
        }
    }
}
