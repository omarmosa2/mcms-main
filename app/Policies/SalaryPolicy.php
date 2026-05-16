<?php

namespace App\Policies;

use App\Models\Salary;
use App\Models\User;

class SalaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('salary.view');
    }

    public function view(User $user, Salary $salary): bool
    {
        return $user->hasPermission('salary.view') && $user->clinic_id === $salary->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('salary.create');
    }

    public function update(User $user, Salary $salary): bool
    {
        return $user->hasPermission('salary.update') && $user->clinic_id === $salary->clinic_id;
    }

    public function delete(User $user, Salary $salary): bool
    {
        return $user->hasPermission('salary.delete') && $user->clinic_id === $salary->clinic_id;
    }
}
