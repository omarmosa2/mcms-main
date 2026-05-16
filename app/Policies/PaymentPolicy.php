<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payment.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payment.view') && $user->clinic_id === $payment->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payment.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payment.update') && $user->clinic_id === $payment->clinic_id;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payment.delete') && $user->clinic_id === $payment->clinic_id;
    }
}
