<?php

namespace App\Actions\Doctors;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListDoctorProfilesAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $status = null,
        ?int $departmentId = null,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
        ?int $doctorScopeUserId = null,
    ): LengthAwarePaginator {
        $query = DoctorProfile::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'user:id,clinic_id,name,email',
                'department:id,clinic_id,name,code,is_active',
            ])
            ->orderByDesc('created_at');

        if ($doctorScopeUserId !== null) {
            $query->where('user_id', $doctorScopeUserId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';

            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('specialty', 'like', $searchTerm)
                    ->orWhere('license_number', 'like', $searchTerm)
                    ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                        $userQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('department', function (Builder $departmentQuery) use ($searchTerm): void {
                        $departmentQuery
                            ->where('name', 'like', $searchTerm)
                            ->orWhere('code', 'like', $searchTerm);
                    });
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $profiles = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'doctor_profiles.index',
            metadata: [
                'per_page' => $perPage,
                'status_filter' => $status,
                'department_filter' => $departmentId,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'doctor_scope_user_id' => $doctorScopeUserId,
                'returned' => $profiles->count(),
            ],
        );

        return $profiles;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'specialty') {
            $query->reorder()->orderBy('specialty', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'license_number') {
            $query->reorder()->orderBy('license_number', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'consultation_duration_minutes') {
            $query
                ->reorder()
                ->orderBy('consultation_duration_minutes', $direction)
                ->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query->reorder()->orderBy('status', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->reorder()->orderBy('created_at', $direction)->orderBy('id', 'desc');
    }
}
