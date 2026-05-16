<?php

namespace App\Actions\Departments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListDepartmentsAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?bool $isActive = null,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = Department::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->withCount('doctorProfiles')
            ->with([
                'creator:id,clinic_id,name',
                'updater:id,clinic_id,name',
            ])
            ->orderByDesc('created_at');

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';

            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('name', 'like', $searchTerm)
                    ->orWhere('code', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $departments = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'departments.index',
            metadata: [
                'per_page' => $perPage,
                'is_active_filter' => $isActive,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'returned' => $departments->count(),
            ],
        );

        return $departments;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'name') {
            $query->reorder()->orderBy('name', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'code') {
            $query->reorder()->orderBy('code', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'is_active') {
            $query->reorder()->orderBy('is_active', $direction)->orderBy('name');

            return;
        }

        if ($sortBy === 'doctor_profiles_count') {
            $query
                ->reorder()
                ->orderBy('doctor_profiles_count', $direction)
                ->orderBy('name');

            return;
        }

        $query->reorder()->orderBy('created_at', $direction)->orderBy('id', 'desc');
    }
}
