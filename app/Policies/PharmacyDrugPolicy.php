<?php

namespace App\Policies;

use App\Models\PharmacyDrug;
use App\Models\User;

class PharmacyDrugPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pharmacy.drug.view');
    }

    public function view(User $user, PharmacyDrug $pharmacyDrug): bool
    {
        return $user->hasPermission('pharmacy.drug.view') && $user->clinic_id === $pharmacyDrug->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pharmacy.drug.create');
    }

    public function update(User $user, PharmacyDrug $pharmacyDrug): bool
    {
        return $user->hasPermission('pharmacy.drug.update') && $user->clinic_id === $pharmacyDrug->clinic_id;
    }

    public function delete(User $user, PharmacyDrug $pharmacyDrug): bool
    {
        return $user->hasPermission('pharmacy.drug.delete') && $user->clinic_id === $pharmacyDrug->clinic_id;
    }
}
