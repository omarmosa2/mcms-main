<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CallNextQueueEntryAction;
use App\Actions\Queue\DeleteQueueEntryAction;
use App\Actions\Queue\EnqueuePatientAction;
use App\Actions\Queue\ListQueueEntriesAction;
use App\Actions\Queue\ShowQueueEntryAction;
use App\Actions\Queue\UpdateQueueEntryStatusAction;
use App\Events\QueueUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\StoreQueueEntryRequest;
use App\Http\Requests\Queue\UpdateQueueEntryStatusRequest;
use App\Http\Resources\QueueEntryResource;
use App\Models\QueueEntry;
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

class QueueEntryController extends Controller
{
    public function __construct(
        private ListQueueEntriesAction $listQueueEntriesAction,
        private ShowQueueEntryAction $showQueueEntryAction,
        private EnqueuePatientAction $enqueuePatientAction,
        private UpdateQueueEntryStatusAction $updateQueueEntryStatusAction,
        private CallNextQueueEntryAction $callNextQueueEntryAction,
        private DeleteQueueEntryAction $deleteQueueEntryAction,
        private CacheService $cacheService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $entries = $this->listQueueEntriesAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            status: $filters['status'],
            queueDate: $filters['queue_date'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
            doctorId: $doctorScopeUserId,
        );

        $entriesResource = QueueEntryResource::collection($entries);

        if ($request->expectsJson()) {
            return $entriesResource;
        }

        $patients = $this->cacheService->getPatientsDropdown($clinicId);

        $appointments = $this->cacheService->getAppointmentsDropdown($clinicId);

        $doctors = $this->cacheService->getDoctorsDropdown($clinicId);

        return Inertia::render('queue/Index', [
            'queue_entries' => $entriesResource->response()->getData(true),
            'patients' => $patients,
            'appointments' => $appointments,
            'doctors' => $doctors,
            'status_options' => [
                QueueEntry::STATUS_WAITING,
                QueueEntry::STATUS_CALLED,
                QueueEntry::STATUS_IN_SERVICE,
                QueueEntry::STATUS_COMPLETED,
                QueueEntry::STATUS_SKIPPED,
                QueueEntry::STATUS_CANCELED,
            ],
            'filters' => $filters,
        ]);
    }

    public function store(StoreQueueEntryRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $entry = $this->enqueuePatientAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return QueueEntryResource::make($entry)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient queued successfully.']);

        QueueUpdated::dispatch($clinicId, 'enqueued', [
            'id' => $entry->id,
            'queue_number' => $entry->queue_number,
            'patient_name' => $entry->patient?->full_name,
        ]);

        return to_route('queue.index');
    }

    public function show(Request $request, int $queueEntryId): QueueEntryResource
    {
        $clinicId = $this->resolveClinicId($request);

        $entry = $this->showQueueEntryAction->handle(
            clinicId: $clinicId,
            queueEntryId: $queueEntryId,
            userId: (int) $request->user()->id,
            doctorId: $this->resolveDoctorScopeUserId($request),
        );

        return QueueEntryResource::make($entry);
    }

    public function updateStatus(UpdateQueueEntryStatusRequest $request, int $queueEntryId): QueueEntryResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $entry = $this->updateQueueEntryStatusAction->handle(
            clinicId: $clinicId,
            queueEntryId: $queueEntryId,
            userId: (int) $request->user()->id,
            newStatus: (string) $request->input('status'),
            notes: $request->input('notes'),
        );

        if ($request->expectsJson()) {
            return QueueEntryResource::make($entry);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Queue status updated successfully.']);

        QueueUpdated::dispatch($clinicId, 'status_updated', [
            'id' => $entry->id,
            'queue_number' => $entry->queue_number,
            'status' => $entry->status,
        ]);

        return to_route('queue.index');
    }

    public function callNext(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $entry = $this->callNextQueueEntryAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            queueDate: $request->string('queue_date')->toString() ?: null,
        );

        if ($entry === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'data' => null,
                    'message' => 'No waiting queue entries found.',
                ]);
            }

            Inertia::flash('toast', ['type' => 'info', 'message' => 'No waiting queue entries found.']);

            return to_route('queue.index');
        }

        if ($request->expectsJson()) {
            return QueueEntryResource::make($entry)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Next patient has been called.']);

        QueueUpdated::dispatch($clinicId, 'called', [
            'id' => $entry->id,
            'queue_number' => $entry->queue_number,
            'patient_name' => $entry->patient?->full_name,
        ]);

        return to_route('queue.index');
    }

    public function destroy(Request $request, int $queueEntryId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteQueueEntryAction->handle(
            clinicId: $clinicId,
            queueEntryId: $queueEntryId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Queue entry removed successfully.']);

        QueueUpdated::dispatch($clinicId, 'removed', ['id' => $queueEntryId]);

        return to_route('queue.index');
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
            foreach (array_values(array_unique($ids)) as $queueEntryId) {
                try {
                    $this->deleteQueueEntryAction->handle(
                        clinicId: $clinicId,
                        queueEntryId: $queueEntryId,
                        userId: $userId,
                    );

                    $deletedIds[] = $queueEntryId;
                } catch (ModelNotFoundException|ValidationException) {
                    $failedIds[] = $queueEntryId;
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected queue entries could be deleted.']);

            return to_route('queue.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d queue entries. %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('queue.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d queue entries successfully.', count($deletedIds)),
        ]);

        return to_route('queue.index');
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
     *     queue_date: ?string,
     *     search: ?string,
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'queue.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     status?: ?string,
         *     queue_date?: ?string,
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
            QueueEntry::STATUS_WAITING,
            QueueEntry::STATUS_CALLED,
            QueueEntry::STATUS_IN_SERVICE,
            QueueEntry::STATUS_COMPLETED,
            QueueEntry::STATUS_SKIPPED,
            QueueEntry::STATUS_CANCELED,
        ]);

        $queueDateInput = $request->exists('queue_date')
            ? $request->query('queue_date')
            : ($savedFilters['queue_date'] ?? null);
        $queueDate = $this->normalizeQueueDate($queueDateInput);

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
            : ($savedFilters['sort_by'] ?? 'queue_date');
        $sortBy = $this->normalizeSortBy($sortByInput);

        $sortDirectionInput = $request->exists('sort_direction')
            ? $request->query('sort_direction')
            : ($savedFilters['sort_direction'] ?? 'desc');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput);

        $filters = [
            'status' => $status,
            'queue_date' => $queueDate,
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

    private function normalizeQueueDate(mixed $value): ?string
    {
        $queueDate = $this->normalizeNullableString($value);

        if ($queueDate === null) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $queueDate) === 1 ? $queueDate : null;
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
            'queue_date',
            'queue_number',
            'priority',
            'status',
            'checked_in_at',
        ];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'queue_date';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
