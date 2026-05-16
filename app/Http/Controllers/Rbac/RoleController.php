<?php

namespace App\Http\Controllers\Rbac;

use App\Actions\Rbac\ListRolesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(
        private ListRolesAction $listRolesAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $roles = $this->listRolesAction->handle($clinicId);

        $permissions = Permission::query()
            ->forClinic($clinicId)
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $p) => $p->group ?? 'other')
            ->map(fn ($group) => $group->map(fn (Permission $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
            ])->values())
            ->toArray();

        $groupedPermissions = collect($permissions)->mapWithKeys(fn ($items, $group) => [
            $group => $items,
        ])->toArray();

        if ($request->expectsJson()) {
            return RoleResource::collection($roles);
        }

        return Inertia::render('roles/Index', [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions_count' => $role->permissions()->count(),
                'users_count' => $role->users()->count(),
                'created_at' => $role->created_at?->toIsoString(),
            ])->values(),
            'permissions' => $groupedPermissions,
            'filters' => [
                'search' => $request->get('search'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create([
            'clinic_id' => $clinicId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            foreach ($validated['permissions'] as $permissionId) {
                $role->permissions()->attach($permissionId, [
                    'clinic_id' => $clinicId,
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role created successfully.']);

        return to_route('roles.index');
    }

    public function update(Request $request, int $roleId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $role = Role::query()
            ->forClinic($clinicId)
            ->where('is_system', false)
            ->findOrFail($roleId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->detach();

        if (! empty($validated['permissions'])) {
            foreach ($validated['permissions'] as $permissionId) {
                $role->permissions()->attach($permissionId, [
                    'clinic_id' => $clinicId,
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role updated successfully.']);

        return to_route('roles.index');
    }

    public function destroy(Request $request, int $roleId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $role = Role::query()
            ->forClinic($clinicId)
            ->where('is_system', false)
            ->findOrFail($roleId);

        if ($role->users()->count() > 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cannot delete role with assigned users.']);

            return to_route('roles.index');
        }

        $role->permissions()->detach();
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role deleted successfully.']);

        return to_route('roles.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
