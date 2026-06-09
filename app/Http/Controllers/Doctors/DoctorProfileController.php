<?php

namespace App\Http\Controllers\Doctors;

use App\Actions\Doctors\CreateDoctorProfileAction;
use App\Actions\Doctors\DeleteDoctorProfileAction;
use App\Actions\Doctors\ListDoctorProfilesAction;
use App\Actions\Doctors\ShowDoctorProfileAction;
use App\Actions\Doctors\UpdateDoctorProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctors\StoreDoctorProfileRequest;
use App\Http\Requests\Doctors\UpdateDoctorProfileRequest;
use App\Http\Resources\DoctorProfileResource;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Services\ClinicWorkingHoursService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DoctorProfileController extends Controller
{
    public function __construct(
        private ListDoctorProfilesAction $listDoctorProfilesAction,
        private ShowDoctorProfileAction $showDoctorProfileAction,
        private CreateDoctorProfileAction $createDoctorProfileAction,
        private UpdateDoctorProfileAction $updateDoctorProfileAction,
        private DeleteDoctorProfileAction $deleteDoctorProfileAction,
        private ClinicWorkingHoursService $clinicWorkingHoursService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $doctorProfiles = $this->listDoctorProfilesAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            status: $filters['status'],
            departmentId: $filters['department_id'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
            doctorScopeUserId: $doctorScopeUserId,
        );

        $doctorProfilesResource = DoctorProfileResource::collection($doctorProfiles);

        if ($request->expectsJson()) {
            return $doctorProfilesResource;
        }

        return Inertia::render('doctors/Index', [
            'doctor_profiles' => $doctorProfilesResource->response()->getData(true),
            'clinic' => $this->resolveClinicOption($clinicId),
            'doctors' => $this->resolveDoctorOptions($clinicId, $doctorScopeUserId),
            'departments' => $this->resolveDepartmentOptions($clinicId),
            'status_options' => $this->statusOptions(),
            'filters' => $filters,
            'is_doctor_scope' => $doctorScopeUserId !== null,
        ]);
    }

    public function store(StoreDoctorProfileRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $doctorProfile = $this->createDoctorProfileAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
            doctorScopeUserId: $doctorScopeUserId,
        );

        if ($request->expectsJson()) {
            return DoctorProfileResource::make($doctorProfile)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Doctor profile created successfully.']);

        return to_route('doctors.index');
    }

    public function show(Request $request, int $doctorProfileId): DoctorProfileResource
    {
        $clinicId = $this->resolveClinicId($request);

        $doctorProfile = $this->showDoctorProfileAction->handle(
            clinicId: $clinicId,
            doctorProfileId: $doctorProfileId,
            userId: (int) $request->user()->id,
            doctorScopeUserId: $this->resolveDoctorScopeUserId($request),
        );

        return DoctorProfileResource::make($doctorProfile);
    }

    public function update(
        UpdateDoctorProfileRequest $request,
        int $doctorProfileId,
    ): DoctorProfileResource|RedirectResponse {
        $clinicId = $this->resolveClinicId($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $doctorProfile = $this->updateDoctorProfileAction->handle(
            clinicId: $clinicId,
            doctorProfileId: $doctorProfileId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
            doctorScopeUserId: $doctorScopeUserId,
        );

        if ($request->expectsJson()) {
            return DoctorProfileResource::make($doctorProfile);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Doctor profile updated successfully.']);

        return to_route('doctors.index');
    }

    public function destroy(Request $request, int $doctorProfileId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $this->deleteDoctorProfileAction->handle(
            clinicId: $clinicId,
            doctorProfileId: $doctorProfileId,
            userId: (int) $request->user()->id,
            doctorScopeUserId: $doctorScopeUserId,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Doctor profile deleted successfully.']);

        return to_route('doctors.index');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $clinicId = $this->resolveClinicId($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);
        $userId = (int) $request->user()->id;

        $deletedIds = [];
        $failedIds = [];

        /** @var array<int> $ids */
        $ids = array_map('intval', $validated['ids']);

        foreach (array_values(array_unique($ids)) as $doctorProfileId) {
            try {
                $this->deleteDoctorProfileAction->handle(
                    clinicId: $clinicId,
                    doctorProfileId: $doctorProfileId,
                    userId: $userId,
                    doctorScopeUserId: $doctorScopeUserId,
                );

                $deletedIds[] = $doctorProfileId;
            } catch (ModelNotFoundException|ValidationException) {
                $failedIds[] = $doctorProfileId;
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected doctor profiles could be deleted.']);

            return to_route('doctors.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d doctor profile(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('doctors.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d doctor profile(s) successfully.', count($deletedIds)),
        ]);

        return to_route('doctors.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    private function resolveDoctorScopeUserId(Request $request): ?int
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole('doctor')) {
            return (int) $user->id;
        }

        return null;
    }

    /**
     * @return array{
     *     status: ?string,
     *     department_id: ?int,
     *     search: ?string,
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'doctors.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     status?: ?string,
         *     department_id?: ?int,
         *     search?: ?string,
         *     per_page?: int,
         *     sort_by?: string,
         *     sort_direction?: string
         * }|null $savedFilters */
        $savedFilters = $request->session()->get($sessionKey);

        $statusInput = $request->exists('status')
            ? $request->query('status')
            : ($savedFilters['status'] ?? null);
        $status = $this->normalizeStatus($statusInput);

        $departmentIdInput = $request->exists('department_id')
            ? $request->query('department_id')
            : ($savedFilters['department_id'] ?? null);
        $departmentId = $this->normalizeNullableInteger($departmentIdInput);

        $searchInput = $request->exists('search')
            ? $request->query('search')
            : ($savedFilters['search'] ?? null);
        $search = $this->normalizeNullableString($searchInput);

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
            'status' => $status,
            'department_id' => $departmentId,
            'search' => $search,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $request->session()->put($sessionKey, $filters);

        return $filters;
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $status = $this->normalizeNullableString($value);

        if ($status === null) {
            return null;
        }

        return in_array($status, $this->statusOptions(), true) ? $status : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
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
            'specialty',
            'license_number',
            'consultation_duration_minutes',
            'status',
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
     * @return array<int, string>
     */
    private function statusOptions(): array
    {
        return [
            DoctorProfile::STATUS_ACTIVE,
            DoctorProfile::STATUS_ON_LEAVE,
            DoctorProfile::STATUS_INACTIVE,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null}>
     */
    private function resolveDoctorOptions(int $clinicId, ?int $doctorScopeUserId): array
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', function (Builder $builder) use ($clinicId): void {
                $builder
                    ->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->when($doctorScopeUserId !== null, function (Builder $builder) use ($doctorScopeUserId): void {
                $builder->whereKey($doctorScopeUserId);
            })
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->limit(250)
            ->get()
            ->map(fn (User $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'email' => $doctor->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string|null}
     */
    private function resolveClinicOption(int $clinicId): array
    {
        $clinic = Clinic::query()
            ->select(['id', 'name'])
            ->findOrFail($clinicId);

        return [
            'id' => (int) $clinic->id,
            'name' => $clinic->name,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, code: string|null, is_active: bool, working_hours: array<int, array{day_of_week: string, is_active: bool, start_time: ?string, end_time: ?string}>}>
     */
    private function resolveDepartmentOptions(int $clinicId): array
    {
        return Department::query()
            ->forClinic($clinicId)
            ->select(['id', 'name', 'code', 'is_active'])
            ->with('workingHours')
            ->orderBy('name')
            ->limit(250)
            ->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'is_active' => (bool) $department->is_active,
                'working_hours' => $this->clinicWorkingHoursService->getForDepartment($department->id),
            ])
            ->values()
            ->all();
    }
}
