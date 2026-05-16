<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('expense.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expense.view') && $user->clinic_id === $expense->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('expense.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expense.update') && $user->clinic_id === $expense->clinic_id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expense.delete') && $user->clinic_id === $expense->clinic_id;
    }
}
