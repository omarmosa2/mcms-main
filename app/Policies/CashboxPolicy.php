<?php

namespace App\Policies;

use App\Models\Cashbox;
use App\Models\User;

class CashboxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('cashbox.view');
    }

    public function view(User $user, Cashbox $cashbox): bool
    {
        return $user->hasPermission('cashbox.view') && $user->clinic_id === $cashbox->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('cashbox.create');
    }

    public function update(User $user, Cashbox $cashbox): bool
    {
        return $user->hasPermission('cashbox.update') && $user->clinic_id === $cashbox->clinic_id;
    }

    public function delete(User $user, Cashbox $cashbox): bool
    {
        return $user->hasPermission('cashbox.delete') && $user->clinic_id === $cashbox->clinic_id;
    }
}
