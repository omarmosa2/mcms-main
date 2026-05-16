<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPatientsAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = Patient::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'chronicConditions',
                'allergies',
                'medications',
                'visits.doctor',
                'attachments',
            ]);

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $nationalIdHash = Patient::hashNationalId($search);

            $query->where(function (Builder $builder) use ($searchTerm, $nationalIdHash): void {
                $builder
                    ->where('file_number', 'like', $searchTerm)
                    ->orWhere('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('phone', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('national_id', 'like', $searchTerm);

                if ($nationalIdHash !== null) {
                    $builder->orWhere('national_id_hash', $nationalIdHash);
                }
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $patients = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'patients.index',
            metadata: [
                'per_page' => $perPage,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'returned' => $patients->count(),
            ],
        );

        return $patients;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'file_number') {
            $query->orderBy('file_number', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'full_name') {
            $query
                ->orderBy('first_name', $direction)
                ->orderBy('last_name', $direction)
                ->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'date_of_birth') {
            $query->orderBy('date_of_birth', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'gender') {
            $query->orderBy('gender', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'phone') {
            $query->orderBy('phone', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'email') {
            $query->orderBy('email', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->orderBy('created_at', $direction)->orderBy('id', 'desc');
    }
}
