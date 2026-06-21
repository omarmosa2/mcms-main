<?php

namespace App\Actions\MedicalRecords;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListMedicalRecordsAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $search = null,
        ?int $clinicFilterId = null,
        ?int $doctorId = null,
        ?string $clinicType = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $diagnosis = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = MedicalRecord::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number',
                'clinic:id,name,code',
                'doctor:id,clinic_id,name',
                'creator:id,clinic_id,name',
            ]);

        $user = User::find($userId);
        if ($user && $user->hasRole('doctor')) {
            $query->where('doctor_id', $userId);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('record_number', 'like', $searchTerm)
                    ->orWhere('primary_diagnosis', 'like', $searchTerm)
                    ->orWhere('secondary_diagnosis', 'like', $searchTerm)
                    ->orWhere('chief_complaint', 'like', $searchTerm)
                    ->orWhereHas('patient', function (Builder $q) use ($searchTerm): void {
                        $q->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });

                if (ctype_digit($search)) {
                    $builder->orWhereHas('patient', function (Builder $q) use ($search): void {
                        $q->where('file_number', (int) $search);
                    });
                }
            });
        }

        if ($clinicFilterId !== null) {
            $query->where('clinic_id', $clinicFilterId);
        }

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        if ($clinicType !== null) {
            $query->where('clinic_type', $clinicType);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($dateFrom !== null) {
            $query->whereDate('visit_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('visit_date', '<=', $dateTo);
        }

        if ($diagnosis !== null) {
            $query->where(function (Builder $builder) use ($diagnosis): void {
                $searchTerm = '%'.trim($diagnosis).'%';
                $builder
                    ->where('primary_diagnosis', 'like', $searchTerm)
                    ->orWhere('secondary_diagnosis', 'like', $searchTerm);
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $records = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'medical_records.index',
            metadata: [
                'per_page' => $perPage,
                'search' => $search,
                'returned' => $records->count(),
            ],
        );

        return $records;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'visit_date') {
            $query->orderBy('visit_date', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'patient_name') {
            $query->orderBy('patient_id', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query->orderBy('status', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->orderBy('created_at', $direction)->orderBy('id', 'desc');
    }
}
