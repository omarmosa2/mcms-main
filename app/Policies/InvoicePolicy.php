<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoice.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoice.view') && $user->clinic_id === $invoice->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoice.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoice.update') && $user->clinic_id === $invoice->clinic_id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoice.delete') && $user->clinic_id === $invoice->clinic_id;
    }
}
