<?php

namespace App\Actions\Expenses;

use App\Actions\BaseAction;
use App\Models\ExpenseCategory;
use App\Services\Cache\CacheService;
use Illuminate\Database\Eloquent\Collection;

class ListExpenseCategoriesAction extends BaseAction
{
    public function __construct(private CacheService $cacheService) {}

    public function handle(
        int $clinicId,
        ?bool $activeOnly = true,
    ): Collection {
        if ($activeOnly === true) {
            return $this->cacheService->getClinicExpenseCategories($clinicId);
        }

        return ExpenseCategory::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->orderBy('name')
            ->get();
    }
}
