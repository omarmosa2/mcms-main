<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateInvoiceAction;
use App\Actions\Billing\DeleteInvoiceAction;
use App\Actions\Billing\IssueInvoiceAction;
use App\Actions\Billing\ListInvoicesAction;
use App\Actions\Billing\ShowInvoiceAction;
use App\Actions\Billing\UpdateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\IssueInvoiceRequest;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Requests\Billing\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
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

class InvoiceController extends Controller
{
    public function __construct(
        private ListInvoicesAction $listInvoicesAction,
        private ShowInvoiceAction $showInvoiceAction,
        private CreateInvoiceAction $createInvoiceAction,
        private UpdateInvoiceAction $updateInvoiceAction,
        private IssueInvoiceAction $issueInvoiceAction,
        private DeleteInvoiceAction $deleteInvoiceAction,
        private CacheService $cacheService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $invoices = $this->listInvoicesAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            status: $filters['status'],
            patientId: $filters['patient_id'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $invoicesResource = InvoiceResource::collection($invoices);

        if ($request->expectsJson()) {
            return $invoicesResource;
        }

        $patients = $this->cacheService->getPatientsDropdown($clinicId);

        $appointments = $this->cacheService->getAppointmentsDropdown($clinicId);

        return Inertia::render('billing/Index', [
            'invoices' => $invoicesResource->response()->getData(true),
            'patients' => $patients,
            'appointments' => $appointments,
            'status_options' => [
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_PAID,
            ],
            'payment_method_options' => [
                'cash',
                'card',
                'bank_transfer',
                'insurance',
                'online',
            ],
            'filters' => $filters,
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $invoice = $this->createInvoiceAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return InvoiceResource::make($invoice)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice created successfully.']);

        return to_route('billing.invoices.index');
    }

    public function show(Request $request, int $invoiceId): InvoiceResource
    {
        $clinicId = $this->resolveClinicId($request);

        $invoice = $this->showInvoiceAction->handle(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            userId: (int) $request->user()->id,
        );

        return InvoiceResource::make($invoice);
    }

    public function update(UpdateInvoiceRequest $request, int $invoiceId): InvoiceResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $invoice = $this->updateInvoiceAction->handle(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return InvoiceResource::make($invoice);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice updated successfully.']);

        return to_route('billing.invoices.index');
    }

    public function issue(IssueInvoiceRequest $request, int $invoiceId): InvoiceResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $invoice = $this->issueInvoiceAction->handle(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return InvoiceResource::make($invoice);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice issued successfully.']);

        return to_route('billing.invoices.index');
    }

    public function destroy(Request $request, int $invoiceId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteInvoiceAction->handle(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice deleted successfully.']);

        return to_route('billing.invoices.index');
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
            foreach (array_values(array_unique($ids)) as $invoiceId) {
                try {
                    $this->deleteInvoiceAction->handle(
                        clinicId: $clinicId,
                        invoiceId: $invoiceId,
                        userId: $userId,
                    );

                    $deletedIds[] = $invoiceId;
                } catch (ModelNotFoundException|ValidationException) {
                    $failedIds[] = $invoiceId;
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected invoices could be deleted.']);

            return to_route('billing.invoices.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d invoice(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('billing.invoices.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d invoice(s) successfully.', count($deletedIds)),
        ]);

        return to_route('billing.invoices.index');
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
     *     status: ?string,
     *     patient_id: ?int,
     *     search: ?string,
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'billing.invoices.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     status?: ?string,
         *     patient_id?: ?int,
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
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ]);

        $patientIdInput = $request->exists('patient_id')
            ? $request->query('patient_id')
            : ($savedFilters['patient_id'] ?? null);
        $patientId = $this->normalizeNullableInteger($patientIdInput);

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
            : ($savedFilters['sort_by'] ?? 'issued_at');
        $sortBy = $this->normalizeSortBy($sortByInput);

        $sortDirectionInput = $request->exists('sort_direction')
            ? $request->query('sort_direction')
            : ($savedFilters['sort_direction'] ?? 'desc');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput);

        $filters = [
            'status' => $status,
            'patient_id' => $patientId,
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

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
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
            'invoice_number',
            'status',
            'issued_at',
            'due_at',
            'total_amount',
            'balance_amount',
        ];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'issued_at';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
