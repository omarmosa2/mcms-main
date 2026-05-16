<?php

namespace App\Policies;

use App\Models\PaymentPlan;
use App\Models\User;

class PaymentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payment.plan.view');
    }

    public function view(User $user, PaymentPlan $paymentPlan): bool
    {
        return $user->hasPermission('payment.plan.view') && $user->clinic_id === $paymentPlan->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payment.plan.create');
    }

    public function update(User $user, PaymentPlan $paymentPlan): bool
    {
        return $user->hasPermission('payment.plan.update') && $user->clinic_id === $paymentPlan->clinic_id;
    }

    public function delete(User $user, PaymentPlan $paymentPlan): bool
    {
        return $user->hasPermission('payment.plan.delete') && $user->clinic_id === $paymentPlan->clinic_id;
    }
}
