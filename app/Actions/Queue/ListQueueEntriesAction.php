<?php

namespace App\Actions\Queue;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListQueueEntriesAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $status = null,
        ?string $queueDate = null,
        ?string $search = null,
        string $sortBy = 'queue_date',
        string $sortDirection = 'desc',
        ?int $doctorId = null,
    ): LengthAwarePaginator {
        $query = QueueEntry::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'appointment:id,clinic_id,appointment_number',
                'assignedDoctor:id,clinic_id,name',
            ])
            ->orderByDesc('queue_date')
            ->orderByDesc('priority')
            ->orderBy('queue_number');

        $user = User::find($userId);
        if ($user && $user->hasRole('doctor')) {
            $query->where('assigned_doctor_id', $userId)
                ->orWhereHas('visit', function (Builder $visitQuery) use ($userId): void {
                    $visitQuery->where('doctor_id', $userId);
                });
        } elseif ($doctorId !== null) {
            $query->where(function (Builder $builder) use ($doctorId): void {
                $builder
                    ->where('assigned_doctor_id', $doctorId)
                    ->orWhereHas('visit', function (Builder $visitQuery) use ($doctorId): void {
                        $visitQuery->where('doctor_id', $doctorId);
                    });
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($queueDate !== null) {
            $query->whereDate('queue_date', $queueDate);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $queueNumberSearch = is_numeric($search) ? (int) $search : null;

            $query->where(function (Builder $builder) use ($searchTerm, $queueNumberSearch): void {
                $builder
                    ->whereHas('patient', function (Builder $patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    })
                    ->orWhereHas('appointment', function (Builder $appointmentQuery) use ($searchTerm): void {
                        $appointmentQuery->where('appointment_number', 'like', $searchTerm);
                    })
                    ->orWhereHas('assignedDoctor', function (Builder $doctorQuery) use ($searchTerm): void {
                        $doctorQuery->where('name', 'like', $searchTerm);
                    });

                if ($queueNumberSearch !== null) {
                    $builder->orWhere('queue_number', $queueNumberSearch);
                }
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $entries = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'queue.index',
            metadata: [
                'status_filter' => $status,
                'queue_date_filter' => $queueDate,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'doctor_scope_user_id' => $doctorId,
                'returned' => $entries->count(),
            ],
        );

        return $entries;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'queue_number') {
            $query
                ->reorder()
                ->orderBy('queue_number', $direction)
                ->orderBy('queue_date', 'desc')
                ->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'priority') {
            $query
                ->reorder()
                ->orderBy('priority', $direction)
                ->orderBy('queue_date', 'desc')
                ->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query
                ->reorder()
                ->orderBy('status', $direction)
                ->orderBy('queue_date', 'desc')
                ->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'checked_in_at') {
            $query
                ->reorder()
                ->orderBy('checked_in_at', $direction)
                ->orderBy('id', 'desc');

            return;
        }

        $query
            ->reorder()
            ->orderBy('queue_date', $direction)
            ->orderBy('priority', 'desc')
            ->orderBy('queue_number');
    }
}
