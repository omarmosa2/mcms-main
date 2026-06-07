<?php

use App\Models\Clinic;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const NEW_PERMISSIONS = [
        'settings.view',
        'settings.manage',
    ];

    /**
     * @var list<string>
     */
    private const ROLES_WITH_SETTINGS = [
        'super_admin',
        'clinic_admin',
        'admin',
    ];

    public function up(): void
    {
        $clinics = Clinic::query()->pluck('id');

        foreach ($clinics as $clinicId) {
            $permissionIds = [];

            foreach (self::NEW_PERMISSIONS as $permissionName) {
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

            foreach (self::ROLES_WITH_SETTINGS as $roleName) {
                $role = Role::query()
                    ->where('clinic_id', $clinicId)
                    ->where('name', $roleName)
                    ->first();

                if ($role === null) {
                    continue;
                }

                $syncData = [];

                foreach ($permissionIds as $permissionId) {
                    $syncData[$permissionId] = ['clinic_id' => $clinicId];
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
