<?php

namespace App\Actions\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListVisitsAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $status = null,
        ?string $search = null,
        string $sortBy = 'started_at',
        string $sortDirection = 'desc',
        ?int $doctorId = null,
    ): LengthAwarePaginator {
        $query = Visit::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'doctor:id,clinic_id,name',
                'appointment:id,clinic_id,appointment_number',
                'queueEntry:id,clinic_id,queue_number,queue_date,status',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        $user = User::find($userId);
        if ($user && $user->hasRole('doctor')) {
            $query->where('doctor_id', $userId);
        } elseif ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $queueNumberSearch = is_numeric($search) ? (int) $search : null;

            $query->where(function (Builder $builder) use ($searchTerm, $queueNumberSearch): void {
                $builder
                    ->where('visit_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function (Builder $patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    })
                    ->orWhereHas('doctor', function (Builder $doctorQuery) use ($searchTerm): void {
                        $doctorQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('appointment', function (Builder $appointmentQuery) use ($searchTerm): void {
                        $appointmentQuery->where('appointment_number', 'like', $searchTerm);
                    });

                if ($queueNumberSearch !== null) {
                    $builder->orWhereHas('queueEntry', function (Builder $queueQuery) use ($queueNumberSearch): void {
                        $queueQuery->where('queue_number', $queueNumberSearch);
                    });
                }
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $visits = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'visits.index',
            metadata: [
                'status_filter' => $status,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'doctor_scope_user_id' => $doctorId,
                'returned' => $visits->count(),
            ],
        );

        return $visits;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'visit_number') {
            $query->reorder()->orderBy('visit_number', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query->reorder()->orderBy('status', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'completed_at') {
            $query->reorder()->orderBy('completed_at', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->reorder()->orderBy('started_at', $direction)->orderBy('id', 'desc');
    }
}
