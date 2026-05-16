<?php

namespace App\Actions\Financial;

use App\Actions\BaseAction;
use App\Models\PaymentPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPaymentPlansAction extends BaseAction
{
    public function handle(
        int $clinicId,
        int $perPage = 15,
        ?bool $isActive = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = PaymentPlan::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with('creator:id,clinic_id,name')
            ->orderByDesc('created_at');

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function ($q) use ($searchTerm): void {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        return $query->paginate($perPage);
    }
}
