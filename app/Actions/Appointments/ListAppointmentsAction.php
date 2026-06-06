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
        ?int $departmentId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): LengthAwarePaginator {
        $query = Appointment::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth',
                'doctor:id,clinic_id,name',
                'doctor.doctorProfile:id,clinic_id,user_id,department_id,specialty,status',
                'doctor.doctorProfile.department:id,clinic_id,name',
            ])
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

        if ($departmentId !== null) {
            $query->whereHas('doctor.doctorProfile', function (Builder $doctorProfileQuery) use ($departmentId): void {
                $doctorProfileQuery->where('department_id', $departmentId);
            });
        }

        if ($dateFrom !== null) {
            $query->whereDate('scheduled_for', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('scheduled_for', '<=', $dateTo);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';

            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('appointment_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function (Builder $patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm)
                            ->orWhere('file_number', 'like', $searchTerm);
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
                'doctor_filter_id' => $doctorId,
                'department_filter_id' => $departmentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
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
