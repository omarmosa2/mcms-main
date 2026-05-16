<?php

namespace App\Http\Controllers\Departments;

use App\Actions\Departments\CreateDepartmentAction;
use App\Actions\Departments\DeleteDepartmentAction;
use App\Actions\Departments\ListDepartmentsAction;
use App\Actions\Departments\ShowDepartmentAction;
use App\Actions\Departments\UpdateDepartmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private ListDepartmentsAction $listDepartmentsAction,
        private ShowDepartmentAction $showDepartmentAction,
        private CreateDepartmentAction $createDepartmentAction,
        private UpdateDepartmentAction $updateDepartmentAction,
        private DeleteDepartmentAction $deleteDepartmentAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $departments = $this->listDepartmentsAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            isActive: $filters['is_active'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $departmentsResource = DepartmentResource::collection($departments);

        if ($request->expectsJson()) {
            return $departmentsResource;
        }

        return Inertia::render('departments/Index', [
            'departments' => $departmentsResource->response()->getData(true),
            'filters' => $filters,
        ]);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $department = $this->createDepartmentAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return DepartmentResource::make($department)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Department created successfully.']);

        return to_route('departments.index');
    }

    public function show(Request $request, int $departmentId): DepartmentResource
    {
        $clinicId = $this->resolveClinicId($request);

        $department = $this->showDepartmentAction->handle(
            clinicId: $clinicId,
            departmentId: $departmentId,
            userId: (int) $request->user()->id,
        );

        return DepartmentResource::make($department);
    }

    public function update(
        UpdateDepartmentRequest $request,
        int $departmentId,
    ): DepartmentResource|RedirectResponse {
        $clinicId = $this->resolveClinicId($request);

        $department = $this->updateDepartmentAction->handle(
            clinicId: $clinicId,
            departmentId: $departmentId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return DepartmentResource::make($department);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Department updated successfully.']);

        return to_route('departments.index');
    }

    public function destroy(Request $request, int $departmentId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteDepartmentAction->handle(
            clinicId: $clinicId,
            departmentId: $departmentId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Department deleted successfully.']);

        return to_route('departments.index');
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

        /** @var array<int> $ids */
        $ids = array_map('intval', $validated['ids']);

        foreach (array_values(array_unique($ids)) as $departmentId) {
            try {
                $this->deleteDepartmentAction->handle(
                    clinicId: $clinicId,
                    departmentId: $departmentId,
                    userId: $userId,
                );

                $deletedIds[] = $departmentId;
            } catch (ModelNotFoundException|ValidationException) {
                $failedIds[] = $departmentId;
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'deleted_ids' => $deletedIds,
                    'failed_ids' => $failedIds,
                    'deleted_count' => count($deletedIds),
                    'failed_count' => count($failedIds),
                ],
            ], count($deletedIds) > 0 ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($deletedIds) === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected departments could be deleted.']);

            return to_route('departments.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d department(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('departments.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d department(s) successfully.', count($deletedIds)),
        ]);

        return to_route('departments.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array{
     *     search: ?string,
     *     is_active: ?bool,
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'departments.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     search?: ?string,
         *     is_active?: ?bool,
         *     per_page?: int,
         *     sort_by?: string,
         *     sort_direction?: string
         * }|null $savedFilters */
        $savedFilters = $request->session()->get($sessionKey);

        $searchInput = $request->exists('search')
            ? $request->query('search')
            : ($savedFilters['search'] ?? null);
        $search = $this->normalizeNullableString($searchInput);

        $isActiveInput = $request->exists('is_active')
            ? $request->query('is_active')
            : ($savedFilters['is_active'] ?? null);
        $isActive = $this->normalizeNullableBoolean($isActiveInput);

        $perPageInput = $request->exists('per_page')
            ? $request->query('per_page')
            : ($savedFilters['per_page'] ?? 15);
        $perPage = $this->normalizePerPage($perPageInput);

        $sortByInput = $request->exists('sort_by')
            ? $request->query('sort_by')
            : ($savedFilters['sort_by'] ?? 'created_at');
        $sortBy = $this->normalizeSortBy($sortByInput);

        $sortDirectionInput = $request->exists('sort_direction')
            ? $request->query('sort_direction')
            : ($savedFilters['sort_direction'] ?? 'desc');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput);

        $filters = [
            'search' => $search,
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

    private function normalizeNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
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
        $allowedSortByValues = [
            'name',
            'code',
            'is_active',
            'doctor_profiles_count',
            'created_at',
        ];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'created_at';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
