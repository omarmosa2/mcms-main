<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListAppointmentsAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $status = null,
        ?string $search = null,
        string $sortBy = 'scheduled_for',
        string $sortDirection = 'desc',
        ?int $doctorId = null,
    ): LengthAwarePaginator {
        $query = Appointment::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with(['patient:id,clinic_id,first_name,last_name', 'doctor:id,clinic_id,name'])
            ->orderByDesc('scheduled_for');

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

            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('appointment_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function (Builder $patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    })
                    ->orWhereHas('doctor', function (Builder $doctorQuery) use ($searchTerm): void {
                        $doctorQuery->where('name', 'like', $searchTerm);
                    });
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $appointments = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'appointments.index',
            metadata: [
                'per_page' => $perPage,
                'status_filter' => $status,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'doctor_scope_user_id' => $doctorId,
                'returned' => $appointments->count(),
            ],
        );

        return $appointments;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'appointment_number') {
            $query->reorder()->orderBy('appointment_number', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'duration_minutes') {
            $query->reorder()->orderBy('duration_minutes', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query->reorder()->orderBy('status', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->reorder()->orderBy('scheduled_for', $direction)->orderBy('id', 'desc');
    }
}
