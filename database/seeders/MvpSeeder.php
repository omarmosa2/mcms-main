<?php

namespace Database\Seeders;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MvpSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->firstOrCreate(
            ['code' => 'ADMIN001'],
            [
                'name' => 'Administration Clinic',
                'timezone' => 'Asia/Damascus',
                'currency' => 'SYP',
                'is_active' => true,
            ],
        );

        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $this->seedUser(
            clinic: $clinic,
            name: 'Demo Admin',
            email: 'demo.admin@example.com',
            roleName: 'clinic_admin',
            assignedBy: null,
        );
    }

    private function seedUser(
        Clinic $clinic,
        string $name,
        string $email,
        string $roleName,
        ?int $assignedBy,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'clinic_id' => $clinic->id,
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $role = app(AssignUserRoleAction::class)->handle($user, $roleName, $assignedBy);

        DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('clinic_id', '!=', $clinic->id)
            ->delete();
        $user->roles()->detach($role->id);
        $user->roles()->attach($role->id, [
            'clinic_id' => $clinic->id,
            'assigned_by' => $assignedBy,
        ]);
        $user->invalidatePermissionCache();

        return $user;
    }
}
