<?php

namespace App\Http\Controllers\Visits;

use App\Actions\Visits\DeleteVisitAction;
use App\Actions\Visits\ListVisitsAction;
use App\Actions\Visits\ShowVisitAction;
use App\Actions\Visits\StartVisitAction;
use App\Actions\Visits\TransitionVisitStatusAction;
use App\Actions\Visits\UpdateVisitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visits\StoreVisitRequest;
use App\Http\Requests\Visits\TransitionVisitStatusRequest;
use App\Http\Requests\Visits\UpdateVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use App\Services\Cache\CacheService;
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

class VisitController extends Controller
{
    public function __construct(
        private ListVisitsAction $listVisitsAction,
        private ShowVisitAction $showVisitAction,
        private StartVisitAction $startVisitAction,
        private UpdateVisitAction $updateVisitAction,
        private TransitionVisitStatusAction $transitionVisitStatusAction,
        private DeleteVisitAction $deleteVisitAction,
        private CacheService $cacheService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $visits = $this->listVisitsAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            status: $filters['status'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
            doctorId: $doctorScopeUserId,
        );

        $visitsResource = VisitResource::collection($visits);

        if ($request->expectsJson()) {
            return $visitsResource;
        }

        $patients = $this->cacheService->getPatientsDropdown($clinicId);

        $appointments = $this->cacheService->getAppointmentsDropdown($clinicId, $doctorScopeUserId);

        $queueEntries = $this->cacheService->getQueueEntriesDropdown($clinicId, $doctorScopeUserId);

        $doctors = $this->cacheService->getDoctorsDropdown($clinicId);

        return Inertia::render('visits/Index', [
            'visits' => $visitsResource->response()->getData(true),
            'patients' => $patients,
            'appointments' => $appointments,
            'queue_entries' => $queueEntries,
            'doctors' => $doctors,
            'status_options' => [
                Visit::STATUS_STARTED,
                Visit::STATUS_IN_PROGRESS,
                Visit::STATUS_COMPLETED,
            ],
            'filters' => $filters,
        ]);
    }

    public function store(StoreVisitRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $visit = $this->startVisitAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
            actingDoctorId: $this->resolveDoctorScopeUserId($request),
        );

        if ($request->expectsJson()) {
            return VisitResource::make($visit)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visit started successfully.']);

        return to_route('visits.index');
    }

    public function show(Request $request, int $visitId): VisitResource
    {
        $clinicId = $this->resolveClinicId($request);

        $visit = $this->showVisitAction->handle(
            clinicId: $clinicId,
            visitId: $visitId,
            userId: (int) $request->user()->id,
            doctorId: $this->resolveDoctorScopeUserId($request),
        );

        return VisitResource::make($visit);
    }

    public function update(UpdateVisitRequest $request, int $visitId): VisitResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $visit = $this->updateVisitAction->handle(
            clinicId: $clinicId,
            visitId: $visitId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
            actingDoctorId: $this->resolveDoctorScopeUserId($request),
        );

        if ($request->expectsJson()) {
            return VisitResource::make($visit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visit updated successfully.']);

        return to_route('visits.index');
    }

    public function transitionStatus(TransitionVisitStatusRequest $request, int $visitId): VisitResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $visit = $this->transitionVisitStatusAction->handle(
            clinicId: $clinicId,
            visitId: $visitId,
            userId: (int) $request->user()->id,
            newStatus: (string) $request->input('status'),
            actingDoctorId: $this->resolveDoctorScopeUserId($request),
        );

        if ($request->expectsJson()) {
            return VisitResource::make($visit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visit status updated successfully.']);

        return to_route('visits.index');
    }

    public function destroy(Request $request, int $visitId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteVisitAction->handle(
            clinicId: $clinicId,
            visitId: $visitId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Visit deleted successfully.']);

        return to_route('visits.index');
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

        DB::transaction(function () use ($ids, $clinicId, $userId, &$deletedIds, &$failedIds): void {
            foreach (array_values(array_unique($ids)) as $visitId) {
                try {
                    $this->deleteVisitAction->handle(
                        clinicId: $clinicId,
                        visitId: $visitId,
                        userId: $userId,
                    );

                    $deletedIds[] = $visitId;
                } catch (ModelNotFoundException|ValidationException) {
                    $failedIds[] = $visitId;
                }
            }
        });

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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected visits could be deleted.']);

            return to_route('visits.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d visit(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('visits.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d visit(s) successfully.', count($deletedIds)),
        ]);

        return to_route('visits.index');
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
     *     search: ?string,
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'visits.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     status?: ?string,
         *     search?: ?string,
         *     per_page?: int,
         *     sort_by?: string,
         *     sort_direction?: string
         * }|null $savedFilters */
        $savedFilters = $request->session()->get($sessionKey);

        $statusInput = $request->exists('status')
            ? $request->query('status')
            : ($savedFilters['status'] ?? null);
        $status = $this->normalizeStatus($statusInput, [
            Visit::STATUS_STARTED,
            Visit::STATUS_IN_PROGRESS,
            Visit::STATUS_COMPLETED,
        ]);

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
            : ($savedFilters['sort_by'] ?? 'started_at');
        $sortBy = $this->normalizeSortBy($sortByInput);

        $sortDirectionInput = $request->exists('sort_direction')
            ? $request->query('sort_direction')
            : ($savedFilters['sort_direction'] ?? 'desc');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput);

        $filters = [
            'status' => $status,
            'search' => $search,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $request->session()->put($sessionKey, $filters);

        return $filters;
    }

    /**
     * @param  array<string>  $allowedStatuses
     */
    private function normalizeStatus(mixed $value, array $allowedStatuses): ?string
    {
        $status = $this->normalizeNullableString($value);

        if ($status === null) {
            return null;
        }

        return in_array($status, $allowedStatuses, true) ? $status : null;
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
        $allowedSortByValues = [
            'visit_number',
            'status',
            'started_at',
            'completed_at',
        ];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'started_at';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
