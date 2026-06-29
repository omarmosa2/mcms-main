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
        ?int $clinicId = null,
        ?bool $activeOnly = true,
    ): Collection {
        $query = ExpenseCategory::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed();

        if ($clinicId !== null) {
            $query->where(function ($q) use ($clinicId) {
                $q->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id');
            });
        }

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }
}
