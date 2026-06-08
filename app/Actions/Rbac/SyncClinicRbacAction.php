<?php

namespace App\Actions\Rbac;

use App\Actions\BaseAction;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncClinicRbacAction extends BaseAction
{
    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'super_admin' => [],
        'clinic_admin' => [
            'patients.*',
            'departments.*',
            'doctor_profiles.*',
            'appointments.*',
            'billing.*',
            'payments.*',
            'expenses.*',
            'users.*',
            'employees.*',
            'salaries.*',
            'cashbox.*',
            'accounts.*',
            'reports.view',
            'settings.view',
            'settings.manage',
            'patient.*',
            'department.*',
            'doctor_profile.*',
            'appointment.*',
            'payment.*',
            'expense.*',
            'user.*',
            'salary.*',
            'cashbox.*',
            'medical_record.*',
        ],
        'admin' => [
            'patients.*',
            'departments.*',
            'doctor_profiles.*',
            'appointments.*',
            'billing.*',
            'payments.*',
            'expenses.*',
            'users.*',
            'employees.*',
            'salaries.*',
            'cashbox.*',
            'accounts.*',
            'reports.view',
            'settings.view',
            'settings.manage',
            'patient.*',
            'department.*',
            'doctor_profile.*',
            'appointment.*',
            'payment.*',
            'expense.*',
            'user.*',
            'salary.*',
            'cashbox.*',
            'medical_record.*',
        ],
        'receptionist' => [
            'patient.create',
            'patient.view',
            'patient.update',
            'department.view',
            'doctor_profile.view',
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.arrival',
            'appointment.delete',
        ],
        'doctor' => [
            'appointment.view',
            'medical.notes.create',
            'medical_record.view',
            'medical_record.create',
            'medical_record.update',
            'prescription.view',
            'prescription.create',
            'follow_up.view',
            'follow_up.create',
            'follow_up.update',
            'treatment_plan.view',
            'treatment_plan.create',
            'treatment_plan.update',
        ],
        'accountant' => [
            'billing.view',
            'billing.generate',
            'payment.record',
            'payment.refund',
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'reports.financial',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const ROLE_DESCRIPTIONS = [
        'super_admin' => 'Full access across all clinic capabilities.',
        'clinic_admin' => 'Administrative access for the clinic operational modules.',
        'admin' => 'Administrative access for the clinic operational modules.',
        'receptionist' => 'Front desk access for patients and appointments.',
        'doctor' => 'Clinical access for patient care workflow.',
        'accountant' => 'Financial access for billing, payments, and financial reporting.',
    ];

    /**
     * @var list<string>
     */
    private const SYSTEM_PERMISSIONS = [
        'patient.view',
        'patient.national_id.view',
        'patient.create',
        'patient.update',
        'patient.delete',
        'patient.*',
        'patients.*',
        'department.view',
        'department.create',
        'department.update',
        'department.delete',
        'department.*',
        'departments.*',
        'doctor_profile.view',
        'doctor_profile.create',
        'doctor_profile.update',
        'doctor_profile.delete',
        'doctor_profile.*',
        'doctor_profiles.*',
        'appointment.view',
        'appointment.create',
        'appointment.update',
        'appointment.arrival',
        'appointment.delete',
        'appointment.*',
        'appointments.*',
        'medical.notes.create',
        'medical_record.view',
        'medical_record.create',
        'medical_record.update',
        'medical_record.delete',
        'medical_record.*',
        'prescription.view',
        'prescription.create',
        'prescription.*',
        'follow_up.view',
        'follow_up.create',
        'follow_up.update',
        'follow_up.*',
        'treatment_plan.view',
        'treatment_plan.create',
        'treatment_plan.update',
        'treatment_plan.*',
        'billing.view',
        'billing.generate',
        'billing.*',
        'payment.record',
        'payment.refund',
        'payment.*',
        'payments.*',
        'expenses.view',
        'expenses.create',
        'expenses.update',
        'expenses.delete',
        'expenses.approve',
        'expense.*',
        'expenses.*',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'user.*',
        'users.*',
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'employees.*',
        'salaries.view',
        'salaries.create',
        'salaries.update',
        'salaries.delete',
        'salaries.approve',
        'salaries.pay',
        'salary.*',
        'salaries.*',
        'cashbox.view',
        'cashbox.open',
        'cashbox.close',
        'cashbox.*',
        'cashboxes.*',
        'accounts.view',
        'accounts.create',
        'accounts.update',
        'accounts.delete',
        'account.*',
        'accounts.*',
        'reports.view',
        'reports.financial',
        'settings.view',
        'settings.manage',
    ];

    public function handle(int $clinicId): void
    {
        DB::transaction(function () use ($clinicId): void {
            $permissionIds = [];

            foreach (self::SYSTEM_PERMISSIONS as $permissionName) {
                $permission = Permission::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'name' => $permissionName,
                    ],
                    [
                        'guard_name' => 'web',
                        'description' => null,
                    ],
                );

                $permissionIds[$permissionName] = $permission->id;
            }

            foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissions) {
                $role = Role::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'name' => $roleName,
                    ],
                    [
                        'guard_name' => 'web',
                        'description' => self::ROLE_DESCRIPTIONS[$roleName] ?? null,
                        'is_system' => true,
                    ],
                );

                $permissionPayload = [];

                foreach ($rolePermissions as $permissionName) {
                    $permissionPayload[$permissionIds[$permissionName]] = [
                        'clinic_id' => $clinicId,
                    ];
                }

                $role->permissions()->sync($permissionPayload);
            }

            Cache::forget("clinic:{$clinicId}:roles");
            Cache::forget("clinic:{$clinicId}:roles:list");
            Cache::forget("clinic:{$clinicId}:permissions");
        });
    }
}
