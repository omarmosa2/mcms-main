<?php

namespace App\Http\Controllers\Salaries;

use App\Actions\Salaries\CreateSalaryAction;
use App\Actions\Salaries\DeleteSalaryAction;
use App\Actions\Salaries\ListSalariesAction;
use App\Actions\Salaries\ProcessSalaryPaymentAction;
use App\Actions\Salaries\UpdateSalaryAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class SalaryController extends Controller
{
    public function __construct(
        private ListSalariesAction $listSalariesAction,
        private CreateSalaryAction $createSalaryAction,
        private UpdateSalaryAction $updateSalaryAction,
        private DeleteSalaryAction $deleteSalaryAction,
        private ProcessSalaryPaymentAction $processSalaryPaymentAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $salaries = $this->listSalariesAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            search: $filters['search'],
            status: $filters['status'],
            periodMonth: $filters['period_month'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $salariesResource = SalaryResource::collection($salaries);

        if ($request->expectsJson()) {
            return $salariesResource;
        }

        return Inertia::render('salaries/Index', [
            'salaries' => $salariesResource->response()->getData(true),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'period_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $salary = $this->createSalaryAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return SalaryResource::make($salary)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salary record created successfully.']);

        return to_route('salaries.index');
    }

    public function update(Request $request, int $salaryId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $salary = $this->updateSalaryAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            salaryId: $salaryId,
            payload: $validated,
        );

        if ($request->expectsJson()) {
            return SalaryResource::make($salary);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salary updated successfully.']);

        return to_route('salaries.index');
    }

    public function destroy(Request $request, int $salaryId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteSalaryAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            salaryId: $salaryId,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salary deleted successfully.']);

        return to_route('salaries.index');
    }

    public function approve(Request $request, int $salaryId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $salary = $this->processSalaryPaymentAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            salaryId: $salaryId,
            approve: false,
        );

        if ($request->expectsJson()) {
            return SalaryResource::make($salary);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salary approved successfully.']);

        return to_route('salaries.index');
    }

    public function pay(Request $request, int $salaryId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $salary = $this->processSalaryPaymentAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            salaryId: $salaryId,
            approve: true,
        );

        if ($request->expectsJson()) {
            return SalaryResource::make($salary);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salary payment processed successfully.']);

        return to_route('salaries.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'salaries.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        $savedFilters = $request->session()->get($sessionKey);

        $searchInput = $request->query('search');
        $search = $this->normalizeNullableString($searchInput ?? ($savedFilters['search'] ?? null));

        $statusInput = $request->query('status');
        $status = $this->normalizeNullableString($statusInput ?? ($savedFilters['status'] ?? null));

        $periodMonthInput = $request->query('period_month');
        $periodMonth = $this->normalizeNullableString($periodMonthInput ?? ($savedFilters['period_month'] ?? null));

        $perPageInput = $request->query('per_page');
        $perPage = $this->normalizePerPage($perPageInput ?? ($savedFilters['per_page'] ?? 15));

        $sortByInput = $request->query('sort_by');
        $sortBy = $this->normalizeSortBy($sortByInput ?? ($savedFilters['sort_by'] ?? 'period_month'));

        $sortDirectionInput = $request->query('sort_direction');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput ?? ($savedFilters['sort_direction'] ?? 'desc'));

        return [
            'search' => $search,
            'status' => $status,
            'period_month' => $periodMonth,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];
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
        $allowedSortByValues = ['period_month', 'net_salary', 'status'];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'period_month';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
