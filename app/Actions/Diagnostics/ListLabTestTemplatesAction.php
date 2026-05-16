<?php

namespace App\Actions\Diagnostics;

use App\Actions\BaseAction;
use App\Models\LabTestTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListLabTestTemplatesAction extends BaseAction
{
    public function handle(
        int $clinicId,
        int $perPage = 15,
        ?bool $isActive = null,
        ?string $category = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        $query = LabTestTemplate::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->orderBy('name');

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($category !== null) {
            $query->where('category', $category);
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
