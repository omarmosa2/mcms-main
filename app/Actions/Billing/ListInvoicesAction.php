<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListInvoicesAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $perPage = 15,
        ?string $status = null,
        ?int $patientId = null,
        ?string $search = null,
        string $sortBy = 'issued_at',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $query = Invoice::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'items:id,clinic_id,invoice_id,description,line_total',
                'payments:id,clinic_id,invoice_id,amount,status,paid_at',
            ])
            ->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($patientId !== null) {
            $query->where('patient_id', $patientId);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';

            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder
                    ->where('invoice_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function (Builder $patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        $this->applySorting($query, $sortBy, $sortDirection);

        $invoices = $query->paginate($perPage);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'billing.invoices.index',
            metadata: [
                'status_filter' => $status,
                'patient_filter' => $patientId,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'returned' => $invoices->count(),
            ],
        );

        return $invoices;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'invoice_number') {
            $query->reorder()->orderBy('invoice_number', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'status') {
            $query->reorder()->orderBy('status', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'total_amount') {
            $query->reorder()->orderBy('total_amount', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'balance_amount') {
            $query->reorder()->orderBy('balance_amount', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'due_at') {
            $query->reorder()->orderBy('due_at', $direction)->orderBy('id', 'desc');

            return;
        }

        $query->reorder()->orderBy('issued_at', $direction)->orderBy('id', 'desc');
    }
}
