<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('user.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('user.view') && $user->clinic_id === $model->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('user.update') && $user->clinic_id === $model->clinic_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('user.delete') && $user->clinic_id === $model->clinic_id;
    }
}
