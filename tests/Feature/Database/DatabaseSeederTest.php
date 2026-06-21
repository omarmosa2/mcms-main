<?php

namespace Tests\Feature\Database;

use App\Models\Clinic;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_only_an_administrator_account(): void
    {
        $this->seed();

        $this->assertSame(1, Clinic::query()->count());
        $this->assertGreaterThan(0, Role::query()->count());
        $this->assertGreaterThan(0, Permission::query()->count());

        $this->assertGreaterThan(0, DB::table('role_user')->count());
        $this->assertGreaterThan(0, DB::table('permission_role')->count());

        $this->assertDatabaseHas('users', [
            'email' => 'demo.admin@example.com',
            'clinic_id' => Clinic::query()->where('code', 'ADMIN001')->value('id'),
        ]);
        $this->assertSame(1, User::query()->count());
        $this->assertTrue(User::query()->where('email', 'demo.admin@example.com')->firstOrFail()->hasPermission('department.view'));
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, Clinic::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, DB::table('role_user')->count());
    }
}
