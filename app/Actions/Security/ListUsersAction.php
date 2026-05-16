<?php

namespace App\Actions\Security;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListUsersAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        ?string $roleName = null,
        ?bool $isActive = null,
        string $sortBy = 'name',
        string $sortDirection = 'asc',
    ): LengthAwarePaginator {
        $query = User::query()
            ->where('clinic_id', $clinicId)
            ->withoutTrashed()
            ->with(['roles' => fn ($q) => $q->select('roles.id', 'roles.name')]);

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        }

        if ($roleName !== null) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.name', $roleName));
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $users = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'users.index',
            metadata: [
                'per_page' => $perPage,
                'search' => $search,
                'role_name' => $roleName,
                'returned' => $users->count(),
            ],
        );

        return $users;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'name') {
            $query->orderBy('name', $direction);
        } elseif ($sortBy === 'email') {
            $query->orderBy('email', $direction);
        } elseif ($sortBy === 'is_active') {
            $query->orderBy('is_active', $direction)->orderBy('name', 'asc');
        } elseif ($sortBy === 'created_at') {
            $query->orderBy('created_at', $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }
}
