<?php

namespace App\Observers;

use App\Models\ExpenseCategory;
use App\Services\Cache\CacheService;

class ExpenseCategoryObserver
{
    public function __construct(private CacheService $cacheService) {}

    public function saved(ExpenseCategory $category): void
    {
        $this->cacheService->invalidateClinicExpenseCategories($category->clinic_id);
    }

    public function deleted(ExpenseCategory $category): void
    {
        $this->cacheService->invalidateClinicExpenseCategories($category->clinic_id);
    }
}
