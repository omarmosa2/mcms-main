<?php

namespace App\Actions\Diagnostics;

use App\Actions\BaseAction;
use App\Models\RadiologyStudyType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListRadiologyStudyTypesAction extends BaseAction
{
    public function handle(
        int $clinicId,
        int $perPage = 15,
        ?bool $isActive = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = RadiologyStudyType::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->orderBy('name');

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($search !== null) {
            $searchTerm = '%'.trim($search).'%';
            $query->where(function ($q) use ($searchTerm): void {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('code', 'like', $searchTerm);
            });
        }

        return $query->paginate($perPage);
    }
}
