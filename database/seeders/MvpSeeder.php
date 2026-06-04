<?php

namespace Database\Seeders;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Seeder;

class MvpSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->firstOrCreate(
            ['code' => 'MVP001'],
            [
                'name' => 'Main MVP Clinic',
                'legal_name' => 'Main MVP Clinic LLC',
                'timezone' => 'Asia/Riyadh',
                'currency' => 'SAR',
                'phone' => '000-000-0000',
                'email' => 'clinic@example.com',
                'address' => 'Localhost',
                'is_active' => true,
            ],
        );

        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $admin = $this->seedUser(
            clinic: $clinic,
            name: 'Demo Admin',
            email: 'demo.admin@example.com',
            roleName: 'clinic_admin',
            assignedBy: null,
        );

        $this->seedUser(
            clinic: $clinic,
            name: 'Demo Doctor',
            email: 'demo.doctor@example.com',
            roleName: 'doctor',
            assignedBy: $admin->id,
        );

        $this->seedUser(
            clinic: $clinic,
            name: 'Demo Receptionist',
            email: 'demo.receptionist@example.com',
            roleName: 'receptionist',
            assignedBy: $admin->id,
        );

        $this->seedUser(
            clinic: $clinic,
            name: 'Demo Accountant',
            email: 'demo.accountant@example.com',
            roleName: 'accountant',
            assignedBy: $admin->id,
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

        app(AssignUserRoleAction::class)->handle($user, $roleName, $assignedBy);

        return $user;
    }
}
