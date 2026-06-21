<?php

namespace App\Http\Controllers\Clinics;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicResource;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\Role;
use App\Models\User;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ClinicController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $filters = $this->resolveIndexFilters($request);

        $clinics = Clinic::query()
            ->withCount('employees')
            ->with(['workingHours'])
            ->orderByDesc('created_at');

        if ($filters['is_active'] !== null) {
            $clinics->where('is_active', $filters['is_active']);
        }

        if ($filters['search'] !== null) {
            $searchTerm = '%'.trim($filters['search']).'%';
            $clinics->where(function (Builder $builder) use ($searchTerm): void {
                $builder->where('name', 'like', $searchTerm)
                    ->orWhere('code', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        $this->applySorting($clinics, $filters['sort_by'], $filters['sort_direction']);

        $clinics = $clinics->paginate($filters['per_page']);

        $clinicsResource = ClinicResource::collection($clinics);

        if ($request->expectsJson()) {
            return $clinicsResource;
        }

        return Inertia::render('clinics/Index', [
            'clinics' => $clinicsResource->response()->getData(true),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $workingHours = $validated['working_hours'] ?? [];
        unset($validated['working_hours']);

        $clinic = DB::transaction(function () use ($validated, $workingHours): Clinic {
            $clinic = Clinic::query()->create($validated);

            $this->syncWorkingHours($clinic, $workingHours);

            return $clinic->loadCount(['employees' => function ($query) {
                $query->withoutGlobalScope('clinic');
            }])->load('workingHours');
        });

        $this->assignCreatorToClinic($request->user(), $clinic);

        if ($request->expectsJson()) {
            return ClinicResource::make($clinic)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إنشاء العيادة بنجاح.']);

        return to_route('clinics.index');
    }

    public function show(Request $request, int $clinicId): ClinicResource
    {
        $clinic = Clinic::query()
            ->withCount('employees')
            ->with(['workingHours'])
            ->findOrFail($clinicId);

        return ClinicResource::make($clinic);
    }

    public function update(
        Request $request,
        int $clinicId,
    ): ClinicResource|RedirectResponse {
        $validated = $this->validatePayload($request, $clinicId);
        $workingHours = $validated['working_hours'] ?? [];
        unset($validated['working_hours']);

        $clinic = DB::transaction(function () use ($clinicId, $validated, $workingHours): Clinic {
            $clinic = Clinic::query()->findOrFail($clinicId);
            $clinic->fill($validated);
            $clinic->save();

            $this->syncWorkingHours($clinic, $workingHours);

            return $clinic->loadCount(['employees' => function ($query) {
                $query->withoutGlobalScope('clinic');
            }])->load('workingHours');
        });

        if ($request->expectsJson()) {
            return ClinicResource::make($clinic);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث العيادة بنجاح.']);

        return to_route('clinics.index');
    }

    public function destroy(Request $request, int $clinicId): Response|RedirectResponse
    {
        $clinic = Clinic::query()
            ->withCount(['employees' => function ($query) {
                $query->withoutGlobalScope('clinic');
            }])
            ->findOrFail($clinicId);

        if ($clinic->employees_count > 0) {
            throw ValidationException::withMessages([
                'clinic' => 'لا يمكن حذف العيادة بينما يوجد موظفون مرتبطون بها.',
            ]);
        }

        $clinic->delete();

        $this->reassignUserClinicContext($request->user(), $clinicId);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف العيادة بنجاح.']);

        return to_route('clinics.index');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $deletedIds = [];
        $failedIds = [];

        /** @var array<int> $ids */
        $ids = array_map('intval', $validated['ids']);

        foreach (array_values(array_unique($ids)) as $clinicId) {
            try {
                $clinic = Clinic::query()
                    ->withCount(['employees' => function ($query) {
                        $query->withoutGlobalScope('clinic');
                    }])
                    ->findOrFail($clinicId);

                if ($clinic->employees_count > 0) {
                    $failedIds[] = $clinicId;

                    continue;
                }

                $clinic->delete();
                $deletedIds[] = $clinicId;
            } catch (ModelNotFoundException) {
                $failedIds[] = $clinicId;
            }
        }

        if (count($deletedIds) > 0) {
            $this->reassignUserClinicContext($request->user(), $deletedIds);
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'لم يتم حذف أي عيادة.']);

            return to_route('clinics.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('تم حذف %d عيادة. لم يتم حذف %d عيادة.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('clinics.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('تم حذف %d عيادة بنجاح.', count($deletedIds)),
        ]);

        return to_route('clinics.index');
    }

    /**
     * @param  array<int, array{day_of_week: string, is_active: bool, start_time: ?string, end_time: ?string}>  $workingHours
     */
    private function syncWorkingHours(Clinic $clinic, array $workingHours): void
    {
        foreach ($workingHours as $entry) {
            $dayIndex = WeekDay::toIndex($entry['day_of_week']);

            ClinicWorkingHour::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'day_of_week' => $dayIndex,
                ],
                [
                    'is_active' => (bool) $entry['is_active'],
                    'start_time' => $entry['start_time'] ?? null,
                    'end_time' => $entry['end_time'] ?? null,
                ],
            );
        }
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): void
    {
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'name') {
            $query->reorder()->orderBy('name', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'code') {
            $query->reorder()->orderBy('code', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($sortBy === 'is_active') {
            $query->reorder()->orderBy('is_active', $direction)->orderBy('name');

            return;
        }

        if ($sortBy === 'employees_count') {
            $query->reorder()->orderBy('employees_count', $direction)->orderBy('name');

            return;
        }

        $query->reorder()->orderBy('created_at', $direction)->orderBy('id', 'desc');
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
        $sessionKey = 'clinics.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

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

    private function validatePayload(Request $request, ?int $clinicId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:50', 'unique:clinics,code,'.($clinicId ?? 'NULL')],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'working_hours' => ['sometimes', 'array'],
            'working_hours.*.day_of_week' => ['required', 'string'],
            'working_hours.*.is_active' => ['required', 'boolean'],
            'working_hours.*.start_time' => ['nullable', 'string'],
            'working_hours.*.end_time' => ['nullable', 'string'],
        ]);
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
            'employees_count',
            'created_at',
        ];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'created_at';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }

    /**
     * @param  array<int>|int  $deletedClinicIds
     */
    private function reassignUserClinicContext(User $user, array|int $deletedClinicIds): void
    {
        $ids = is_array($deletedClinicIds) ? $deletedClinicIds : [$deletedClinicIds];

        if ($user->clinic_id === null || ! in_array($user->clinic_id, $ids, true)) {
            return;
        }

        $nextClinic = Clinic::query()
            ->where('is_active', true)
            ->whereNotIn('id', $ids)
            ->orderByDesc('created_at')
            ->first();

        $user->update(['clinic_id' => $nextClinic?->id]);
        $user->invalidatePermissionCache();
    }

    private function assignCreatorToClinic(User $user, Clinic $clinic): void
    {
        if ($user->clinic_id !== null) {
            return;
        }

        $user->update(['clinic_id' => $clinic->id]);

        $superAdminRole = Role::query()
            ->where('clinic_id', $clinic->id)
            ->where('name', 'super_admin')
            ->first();

        if ($superAdminRole !== null) {
            $user->roles()->syncWithoutDetaching([
                $superAdminRole->id => [
                    'clinic_id' => $clinic->id,
                    'assigned_by' => $user->id,
                ],
            ]);
        }

        $user->invalidatePermissionCache();
    }
}
