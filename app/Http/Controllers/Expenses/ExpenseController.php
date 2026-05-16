<?php

namespace App\Http\Controllers\Expenses;

use App\Actions\Expenses\ApproveExpenseAction;
use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\DeleteExpenseAction;
use App\Actions\Expenses\ListExpenseCategoriesAction;
use App\Actions\Expenses\ListExpensesAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private ListExpensesAction $listExpensesAction,
        private ListExpenseCategoriesAction $listExpenseCategoriesAction,
        private CreateExpenseAction $createExpenseAction,
        private UpdateExpenseAction $updateExpenseAction,
        private DeleteExpenseAction $deleteExpenseAction,
        private ApproveExpenseAction $approveExpenseAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $expenses = $this->listExpensesAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            search: $filters['search'],
            status: $filters['status'],
            categoryId: $filters['category_id'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $categories = $this->listExpenseCategoriesAction->handle($clinicId, true);

        $expensesResource = ExpenseResource::collection($expenses);

        if ($request->expectsJson()) {
            return $expensesResource;
        }

        return Inertia::render('expenses/Index', [
            'expenses' => $expensesResource->response()->getData(true),
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $expense = $this->createExpenseAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return ExpenseResource::make($expense)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تسجيل المصروف بنجاح.']);

        return to_route('expenses.index');
    }

    public function show(Request $request, int $expenseId): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $expense = Expense::query()
            ->forClinic($clinicId)
            ->with(['category:id,name', 'user:id,name', 'approver:id,name'])
            ->findOrFail($expenseId);

        if ($request->expectsJson()) {
            return response()->json(['data' => ExpenseResource::make($expense)]);
        }

        return Inertia::render('expenses/Show', [
            'expense' => ExpenseResource::make($expense)->response()->getData(true),
        ]);
    }

    public function update(
        UpdateExpenseRequest $request,
        int $expenseId,
    ): JsonResponse|RedirectResponse {
        $clinicId = $this->resolveClinicId($request);

        $expense = $this->updateExpenseAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            expenseId: $expenseId,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return ExpenseResource::make($expense)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense updated successfully.']);

        return to_route('expenses.index');
    }

    public function destroy(Request $request, int $expenseId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteExpenseAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            expenseId: $expenseId,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense deleted successfully.']);

        return to_route('expenses.index');
    }

    public function approve(Request $request, int $expenseId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $expense = $this->approveExpenseAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            expenseId: $expenseId,
            approve: true,
        );

        if ($request->expectsJson()) {
            return ExpenseResource::make($expense)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense approved successfully.']);

        return to_route('expenses.index');
    }

    public function reject(Request $request, int $expenseId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $expense = $this->approveExpenseAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            expenseId: $expenseId,
            approve: false,
        );

        if ($request->expectsJson()) {
            return ExpenseResource::make($expense)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense rejected.']);

        return to_route('expenses.index');
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

        foreach (array_values(array_unique($ids)) as $expenseId) {
            try {
                $this->deleteExpenseAction->handle(
                    clinicId: $clinicId,
                    userId: $userId,
                    expenseId: $expenseId,
                );

                $deletedIds[] = $expenseId;
            } catch (ModelNotFoundException|ValidationException) {
                $failedIds[] = $expenseId;
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected expenses could be deleted.']);

            return to_route('expenses.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d expense(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('expenses.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d expense(s) successfully.', count($deletedIds)),
        ]);

        return to_route('expenses.index');
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
        $sessionKey = 'expenses.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        $savedFilters = $request->session()->get($sessionKey);

        $searchInput = $request->query('search');
        $search = $this->normalizeNullableString($searchInput ?? ($savedFilters['search'] ?? null));

        $statusInput = $request->query('status');
        $status = $this->normalizeNullableString($statusInput ?? ($savedFilters['status'] ?? null));

        $categoryIdInput = $request->query('category_id');
        $categoryId = $categoryIdInput !== null ? (int) $categoryIdInput : ($savedFilters['category_id'] ?? null);

        $dateFromInput = $request->query('date_from');
        $dateFrom = $this->normalizeNullableString($dateFromInput ?? ($savedFilters['date_from'] ?? null));

        $dateToInput = $request->query('date_to');
        $dateTo = $this->normalizeNullableString($dateToInput ?? ($savedFilters['date_to'] ?? null));

        $perPageInput = $request->query('per_page');
        $perPage = $this->normalizePerPage($perPageInput ?? ($savedFilters['per_page'] ?? 15));

        $sortByInput = $request->query('sort_by');
        $sortBy = $this->normalizeSortBy($sortByInput ?? ($savedFilters['sort_by'] ?? 'created_at'));

        $sortDirectionInput = $request->query('sort_direction');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput ?? ($savedFilters['sort_direction'] ?? 'desc'));

        $filters = [
            'search' => $search,
            'status' => $status,
            'category_id' => $categoryId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
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
        $allowedSortByValues = ['amount', 'expense_date', 'status', 'created_at'];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'created_at';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
