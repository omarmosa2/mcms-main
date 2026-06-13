<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\Compliance\LogSensitiveAccessAction;
use App\Actions\Patients\CreatePatientAction;
use App\Actions\Patients\DeletePatientAction;
use App\Actions\Patients\DeletePatientAttachmentAction;
use App\Actions\Patients\ListPatientsAction;
use App\Actions\Patients\ShowPatientAction;
use App\Actions\Patients\StorePatientAttachmentAction;
use App\Actions\Patients\UpdatePatientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientAttachmentRequest;
use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use App\Http\Resources\PatientAttachmentResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function __construct(
        private ListPatientsAction $listPatientsAction,
        private ShowPatientAction $showPatientAction,
        private CreatePatientAction $createPatientAction,
        private UpdatePatientAction $updatePatientAction,
        private DeletePatientAction $deletePatientAction,
        private StorePatientAttachmentAction $storePatientAttachmentAction,
        private DeletePatientAttachmentAction $deletePatientAttachmentAction,
        private LogSensitiveAccessAction $logSensitiveAccessAction,
        private LogAuditAction $logAuditAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $patients = $this->listPatientsAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $patientsResource = PatientResource::collection($patients);

        if ($request->expectsJson()) {
            return $patientsResource;
        }

        $statsQuery = Patient::query()->forClinic($clinicId);

        $stats = [
            'total' => $statsQuery->count(),
            'male' => (clone $statsQuery)->where('gender', 'male')->count(),
            'female' => (clone $statsQuery)->where('gender', 'female')->count(),
        ];

        return Inertia::render('patients/Index', [
            'patients' => $patientsResource->response()->getData(true),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function store(StorePatientRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $patient = $this->createPatientAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return PatientResource::make($patient)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient created successfully.']);

        return to_route('patients.index');
    }

    public function show(Request $request, int $patientId): PatientResource|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $patient = $this->showPatientAction->handle(
            clinicId: $clinicId,
            patientId: $patientId,
            userId: (int) $request->user()->id,
            accessReason: $request->string('access_reason')->toString() ?: null,
        );

        if ($request->expectsJson()) {
            return PatientResource::make($patient);
        }

        return Inertia::render('patients/Show', [
            'patient' => PatientResource::make($patient)->response()->getData(true),
        ]);
    }

    public function update(UpdatePatientRequest $request, int $patientId): PatientResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $patient = $this->updatePatientAction->handle(
            clinicId: $clinicId,
            patientId: $patientId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return PatientResource::make($patient);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient updated successfully.']);

        return to_route('patients.index');
    }

    public function destroy(Request $request, int $patientId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deletePatientAction->handle(
            clinicId: $clinicId,
            patientId: $patientId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient deleted successfully.']);

        return to_route('patients.index');
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
            foreach (array_values(array_unique($ids)) as $patientId) {
                try {
                    $this->deletePatientAction->handle(
                        clinicId: $clinicId,
                        patientId: $patientId,
                        userId: $userId,
                    );

                    $deletedIds[] = $patientId;
                } catch (ModelNotFoundException|ValidationException) {
                    $failedIds[] = $patientId;
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected patients could be deleted.']);

            return to_route('patients.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d patient(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('patients.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d patient(s) successfully.', count($deletedIds)),
        ]);

        return to_route('patients.index');
    }

    public function storeAttachment(StorePatientAttachmentRequest $request, int $patientId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Attachment file is required.');
        }

        $attachment = $this->storePatientAttachmentAction->handle(
            clinicId: $clinicId,
            patientId: $patientId,
            userId: (int) $request->user()->id,
            file: $file,
        );

        if ($request->expectsJson()) {
            return PatientAttachmentResource::make($attachment)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attachment uploaded successfully.']);

        return to_route('patients.index');
    }

    public function downloadAttachment(Request $request, int $patientId, int $attachmentId): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $patient = Patient::query()
            ->forClinic($clinicId)
            ->findOrFail($patientId);

        $attachment = $patient->attachments()
            ->where('clinic_id', $clinicId)
            ->findOrFail($attachmentId);

        $this->logSensitiveAccessAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            resourceType: 'patient_attachment',
            resourceId: (int) $attachment->id,
            patientId: (int) $patient->id,
            reason: $request->string('access_reason')->toString() ?: null,
            context: [
                'source' => 'patients.attachment.download',
            ],
        );

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'patients.attachment.download',
            auditable: $attachment,
            metadata: [
                'patient_id' => $patient->id,
            ],
        );

        return Storage::disk((string) $attachment->disk)->download(
            (string) $attachment->path,
            (string) $attachment->original_name,
        );
    }

    public function destroyAttachment(Request $request, int $patientId, int $attachmentId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deletePatientAttachmentAction->handle(
            clinicId: $clinicId,
            patientId: $patientId,
            attachmentId: $attachmentId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attachment deleted successfully.']);

        return to_route('patients.index');
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
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'patients.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     search?: ?string,
         *     per_page?: int,
         *     sort_by?: string,
         *     sort_direction?: string
         * }|null $savedFilters */
        $savedFilters = $request->session()->get($sessionKey);

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
            'search' => $search,
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
        $allowedSortByValues = [
            'file_number',
            'full_name',
            'date_of_birth',
            'gender',
            'phone',
            'email',
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
