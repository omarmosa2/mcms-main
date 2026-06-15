<?php

use App\Models\Clinic;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const NEW_PERMISSIONS = [
        'patient_card.view',
        'patient_card.update',
        'patient_card.delete',
        'patient_card.*',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'clinic_admin' => ['patient_card.*'],
        'admin' => ['patient_card.*'],
        'doctor' => ['patient.view', 'patient_card.view', 'patient_card.update'],
    ];

    public function up(): void
    {
        $clinics = Clinic::query()->pluck('id');

        foreach ($clinics as $clinicId) {
            $permissionIds = [];

            foreach ([...self::NEW_PERMISSIONS, 'patient.view'] as $permissionName) {
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

            foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
                $role = Role::query()
                    ->where('clinic_id', $clinicId)
                    ->where('name', $roleName)
                    ->first();

                if ($role === null) {
                    continue;
                }

                $syncData = [];

                foreach ($permissionNames as $permissionName) {
                    $syncData[$permissionIds[$permissionName]] = ['clinic_id' => $clinicId];
                }

                $role->permissions()->syncWithoutDetaching($syncData);
            }

            DB::statement("DELETE FROM `cache` WHERE `key` LIKE 'clinic:{$clinicId}:%'");
        }
    }

    public function down(): void
    {
        $clinics = Clinic::query()->pluck('id');

        foreach ($clinics as $clinicId) {
            Permission::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('name', self::NEW_PERMISSIONS)
                ->delete();
        }
    }
};
