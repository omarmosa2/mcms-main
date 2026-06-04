<?php

namespace Tests\Feature\Cache;

use App\Models\Clinic;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityPolicy;
use App\Models\User;
use App\Services\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_policy_is_cached(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $policy = SecurityPolicy::factory()->for($clinic)->create([
            'password_min_length' => 10,
            'force_two_factor' => true,
        ]);

        $cacheKey = "clinic:{$clinic->id}:security_policy";

        Cache::forget($cacheKey);

        $this->actingAs($user);
        $this->get('/dashboard');

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertIsArray(Cache::get($cacheKey));
        $this->assertArrayHasKey('id', Cache::get($cacheKey));
    }

    public function test_security_policy_cache_is_invalidated_on_update(): void
    {
        $clinic = Clinic::factory()->create();
        $policy = SecurityPolicy::factory()->for($clinic)->create([
            'password_min_length' => 8,
        ]);

        $cacheKey = "clinic:{$clinic->id}:security_policy";

        Cache::put($cacheKey, $policy, now()->addMinutes(30));
        $this->assertTrue(Cache::has($cacheKey));

        $policy->update(['password_min_length' => 12]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_security_policy_cache_is_invalidated_on_delete(): void
    {
        $clinic = Clinic::factory()->create();
        $policy = SecurityPolicy::factory()->for($clinic)->create();

        $cacheKey = "clinic:{$clinic->id}:security_policy";

        Cache::put($cacheKey, $policy, now()->addMinutes(30));
        $this->assertTrue(Cache::has($cacheKey));

        $policy->delete();

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_user_permissions_are_cached(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $role = Role::factory()->for($clinic)->create(['name' => 'doctor_test_'.uniqid()]);
        $permission = Permission::factory()->for($clinic)->create(['name' => 'visit.view']);

        $role->permissions()->attach($permission, ['clinic_id' => $clinic->id]);
        $user->roles()->attach($role, ['clinic_id' => $clinic->id, 'assigned_by' => null]);

        $cacheKey = "clinic:{$clinic->id}:user:{$user->id}:permissions";

        Cache::forget($cacheKey);

        $permissions = $user->getCachedPermissions();

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertIsArray(Cache::get($cacheKey));
        $this->assertTrue($permissions->contains('visit.view'));
    }

    public function test_user_permission_cache_is_invalidated_on_role_change(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();
        $role = Role::factory()->for($clinic)->create(['name' => 'doctor_test_'.uniqid()]);
        $permission = Permission::factory()->for($clinic)->create(['name' => 'visit.view']);

        $role->permissions()->attach($permission, ['clinic_id' => $clinic->id]);
        $user->roles()->attach($role, ['clinic_id' => $clinic->id, 'assigned_by' => null]);

        $cacheKey = "clinic:{$clinic->id}:user:{$user->id}:permissions";

        $user->getCachedPermissions();
        $this->assertTrue(Cache::has($cacheKey));

        $role->delete();

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_roles_are_cached(): void
    {
        $clinic = Clinic::factory()->create();
        $role1 = Role::factory()->make(['name' => 'role_a_'.$clinic->id, 'clinic_id' => $clinic->id]);
        $role1->save();
        $role2 = Role::factory()->make(['name' => 'role_b_'.$clinic->id, 'clinic_id' => $clinic->id]);
        $role2->save();

        $cacheKey = "clinic:{$clinic->id}:roles:list";

        Cache::forget($cacheKey);

        $roles = app(CacheService::class)->getClinicRoles($clinic->id);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertIsArray(Cache::get($cacheKey));
        $this->assertGreaterThanOrEqual(2, $roles->count());
    }

    public function test_roles_cache_is_invalidated_on_change(): void
    {
        $clinic = Clinic::factory()->create();
        $role = Role::factory()->for($clinic)->create(['name' => 'doctor_'.uniqid()]);

        $cacheKey = "clinic:{$clinic->id}:roles:list";

        Cache::put($cacheKey, Role::query()->forClinic($clinic->id)->get(), now()->addMinutes(10));
        $this->assertTrue(Cache::has($cacheKey));

        $role->update(['name' => 'senior_doctor_'.uniqid()]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_departments_are_cached(): void
    {
        $clinic = Clinic::factory()->create();
        Department::factory()->for($clinic)->create(['name' => 'Cardiology', 'is_active' => true]);
        Department::factory()->for($clinic)->create(['name' => 'Neurology', 'is_active' => true]);

        $cacheKey = "clinic:{$clinic->id}:departments:list";

        Cache::forget($cacheKey);

        $departments = app(CacheService::class)->getClinicDepartments($clinic->id);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertIsArray(Cache::get($cacheKey));
        $this->assertCount(2, $departments);
    }

    public function test_departments_cache_is_invalidated_on_change(): void
    {
        $clinic = Clinic::factory()->create();
        $department = Department::factory()->for($clinic)->create(['name' => 'Cardiology']);

        $cacheKey = "clinic:{$clinic->id}:departments:list";

        Cache::put($cacheKey, Department::query()->forClinic($clinic->id)->get(), now()->addMinutes(10));
        $this->assertTrue(Cache::has($cacheKey));

        $department->update(['name' => 'Cardiology Department']);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_expense_categories_are_cached(): void
    {
        $clinic = Clinic::factory()->create();
        ExpenseCategory::factory()->for($clinic)->create(['name' => 'Office Supplies', 'is_active' => true]);
        ExpenseCategory::factory()->for($clinic)->create(['name' => 'Travel', 'is_active' => true]);

        $cacheKey = "clinic:{$clinic->id}:expense_categories:list";

        Cache::forget($cacheKey);

        $categories = app(CacheService::class)->getClinicExpenseCategories($clinic->id);

        $this->assertTrue(Cache::has($cacheKey));
        $this->assertIsArray(Cache::get($cacheKey));
        $this->assertCount(2, $categories);
    }

    public function test_expense_categories_cache_is_invalidated_on_change(): void
    {
        $clinic = Clinic::factory()->create();
        $category = ExpenseCategory::factory()->for($clinic)->create(['name' => 'Office Supplies']);

        $cacheKey = "clinic:{$clinic->id}:expense_categories:list";

        Cache::put($cacheKey, ExpenseCategory::query()->forClinic($clinic->id)->get(), now()->addMinutes(10));
        $this->assertTrue(Cache::has($cacheKey));

        $category->update(['name' => 'Office Supplies & Equipment']);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_cache_keys_are_isolated_between_clinics(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();

        $policy1 = SecurityPolicy::factory()->for($clinic1)->create(['password_min_length' => 8]);
        $policy2 = SecurityPolicy::factory()->for($clinic2)->create(['password_min_length' => 12]);

        $cacheKey1 = "clinic:{$clinic1->id}:security_policy";
        $cacheKey2 = "clinic:{$clinic2->id}:security_policy";

        Cache::put($cacheKey1, $policy1, now()->addMinutes(30));
        Cache::put($cacheKey2, $policy2, now()->addMinutes(30));

        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));

        $policy1->update(['password_min_length' => 10]);

        $this->assertFalse(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));
    }

    public function test_clear_clinic_cache_clears_all_clinic_cache_keys(): void
    {
        $clinic = Clinic::factory()->create();

        Cache::put("clinic:{$clinic->id}:security_policy", 'value', now()->addMinutes(30));
        Cache::put("clinic:{$clinic->id}:roles", 'value', now()->addMinutes(30));
        Cache::put("clinic:{$clinic->id}:departments", 'value', now()->addMinutes(30));
        Cache::put("clinic:{$clinic->id}:dashboard_stats", 'value', now()->addMinutes(30));

        SecurityPolicy::clearClinicCache($clinic->id);

        $this->assertFalse(Cache::has("clinic:{$clinic->id}:security_policy"));
        $this->assertFalse(Cache::has("clinic:{$clinic->id}:roles"));
        $this->assertFalse(Cache::has("clinic:{$clinic->id}:departments"));
        $this->assertFalse(Cache::has("clinic:{$clinic->id}:dashboard_stats"));
    }

    public function test_dashboard_stats_are_cached(): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->for($clinic)->create();

        $cacheKey = "clinic:{$clinic->id}:dashboard_stats";

        Cache::forget($cacheKey);

        $cacheService = app(CacheService::class);
        $stats = $cacheService->getDashboardStats($clinic->id);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_patients', $stats);
        $this->assertArrayHasKey('today_appointments', $stats);
        $this->assertArrayHasKey('pending_queue', $stats);
        $this->assertArrayHasKey('active_visits', $stats);
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_dashboard_stats_cache_is_invalidated(): void
    {
        $clinic = Clinic::factory()->create();

        $cacheKey = "clinic:{$clinic->id}:dashboard_stats";

        $cacheService = app(CacheService::class);
        $cacheService->getDashboardStats($clinic->id);
        $this->assertTrue(Cache::has($cacheKey));

        $cacheService->invalidateDashboardStats($clinic->id);

        $this->assertFalse(Cache::has($cacheKey));
    }
}
