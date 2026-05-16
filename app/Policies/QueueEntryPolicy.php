<?php

namespace App\Policies;

use App\Models\QueueEntry;
use App\Models\User;

class QueueEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('queue.view');
    }

    public function view(User $user, QueueEntry $queueEntry): bool
    {
        return $user->hasPermission('queue.view') && $user->clinic_id === $queueEntry->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('queue.create');
    }

    public function update(User $user, QueueEntry $queueEntry): bool
    {
        return $user->hasPermission('queue.update') && $user->clinic_id === $queueEntry->clinic_id;
    }

    public function delete(User $user, QueueEntry $queueEntry): bool
    {
        return $user->hasPermission('queue.delete') && $user->clinic_id === $queueEntry->clinic_id;
    }
}
