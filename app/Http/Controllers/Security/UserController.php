<?php

namespace App\Http\Controllers\Security;

use App\Actions\Rbac\ListRolesAction;
use App\Actions\Security\CreateUserAction;
use App\Actions\Security\DeleteUserAction;
use App\Actions\Security\ListUsersAction;
use App\Actions\Security\ResetUserPasswordAction;
use App\Actions\Security\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private ListUsersAction $listUsersAction,
        private ListRolesAction $listRolesAction,
        private CreateUserAction $createUserAction,
        private UpdateUserAction $updateUserAction,
        private ResetUserPasswordAction $resetUserPasswordAction,
        private DeleteUserAction $deleteUserAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $users = $this->listUsersAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            search: $filters['search'],
            roleName: $filters['role_name'],
            isActive: $filters['is_active'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $roles = $this->listRolesAction->handle($clinicId);

        $usersResource = UserResource::collection($users);

        if ($request->expectsJson()) {
            return $usersResource;
        }

        return Inertia::render('users/Index', [
            'users' => $usersResource->response()->getData(true),
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_name' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8'],
            'password_confirmation' => ['nullable', 'same:password'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (filled($validated['password'] ?? null) && ! $this->canResetUserPasswords($request)) {
            abort(Response::HTTP_FORBIDDEN, 'Only administrators can reset user passwords.');
        }

        $user = $this->createUserAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return UserResource::make($user)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User created successfully.']);

        return to_route('users.index');
    }

    public function update(Request $request, int $userId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role_name' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = $this->updateUserAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            targetUserId: $userId,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return UserResource::make($user)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated successfully.']);

        return to_route('users.index');
    }

    public function destroy(Request $request, int $userId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteUserAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            targetUserId: $userId,
        );

        if ($request->expectsJson()) {
            return response()->noContent()->setStatusCode(Response::HTTP_NO_CONTENT);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User deleted successfully.']);

        return to_route('users.index');
    }

    public function resetPassword(Request $request, int $userId): JsonResponse|RedirectResponse
    {
        if (! $this->canResetUserPasswords($request)) {
            abort(Response::HTTP_FORBIDDEN, 'Only administrators can reset user passwords.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->resetUserPasswordAction->handle($request->user(), $userId, $validated['password']);

        if ($request->expectsJson()) {
            return UserResource::make($user)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تمت إعادة ضبط كلمة المرور بنجاح.']);

        return to_route('users.index');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $clinicId = $this->resolveClinicId($request);
        $userId = (int) $request->user()->id;

        $deletedIds = [];
        $failedIds = [];

        $ids = array_map('intval', $validated['ids']);

        foreach (array_values(array_unique($ids)) as $targetUserId) {
            try {
                $this->deleteUserAction->handle(
                    clinicId: $clinicId,
                    userId: $userId,
                    targetUserId: $targetUserId,
                );

                $deletedIds[] = $targetUserId;
            } catch (\Exception) {
                $failedIds[] = $targetUserId;
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'deleted_ids' => $deletedIds,
                    'failed_ids' => $failedIds,
                ],
            ], count($deletedIds) > 0 ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = count($deletedIds) > 0
            ? sprintf('Deleted %d user(s) successfully.', count($deletedIds))
            : 'No users could be deleted.';

        $type = count($failedIds) > 0 ? 'warning' : 'success';
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('users.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    private function canResetUserPasswords(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->is_super_admin || $user->isClinicSecurityManager());
    }

    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'users.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        $savedFilters = $request->session()->get($sessionKey);

        $searchInput = $request->query('search');
        $search = $this->normalizeNullableString($searchInput ?? ($savedFilters['search'] ?? null));

        $roleNameInput = $request->query('role_name');
        $roleName = $this->normalizeNullableString($roleNameInput ?? ($savedFilters['role_name'] ?? null));

        $isActiveInput = $request->query('is_active');
        $isActive = $isActiveInput !== null ? (bool) $isActiveInput : ($savedFilters['is_active'] ?? null);

        $perPageInput = $request->query('per_page');
        $perPage = $this->normalizePerPage($perPageInput ?? ($savedFilters['per_page'] ?? 15));

        $sortByInput = $request->query('sort_by');
        $sortBy = $this->normalizeSortBy($sortByInput ?? ($savedFilters['sort_by'] ?? 'name'));

        $sortDirectionInput = $request->query('sort_direction');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput ?? ($savedFilters['sort_direction'] ?? 'asc'));

        $filters = [
            'search' => $search,
            'role_name' => $roleName,
            'is_active' => $isActive,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $request->session()->put($sessionKey, $filters);

        return $filters;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePerPage(mixed $value): int
    {
        $perPage = (int) $value;
        $allowedPerPageValues = [10, 15, 25, 50];

        return in_array($perPage, $allowedPerPageValues, true) ? $perPage : 15;
    }

    private function normalizeSortBy(mixed $value): string
    {
        $sortBy = trim((string) ($value ?? ''));
        $allowedSortByValues = ['name', 'email', 'is_active', 'created_at'];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'name';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }
}
