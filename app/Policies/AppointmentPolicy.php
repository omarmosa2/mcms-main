<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('appointment.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasPermission('appointment.view') && $user->clinic_id === $appointment->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('appointment.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasPermission('appointment.update') && $user->clinic_id === $appointment->clinic_id;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->hasPermission('appointment.delete') && $user->clinic_id === $appointment->clinic_id;
    }
}
