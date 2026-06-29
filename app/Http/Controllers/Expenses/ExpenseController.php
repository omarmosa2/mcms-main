<?php

namespace App\Http\Controllers\Expenses;

use App\Actions\Expenses\ApproveExpenseAction;
use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\DeleteExpenseAction;
use App\Actions\Expenses\ListExpenseCategoriesAction;
use App\Actions\Expenses\ListExpensesAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Exports\ExpenseExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Clinic;
use App\Models\Expense;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse|StreamedResponse
    {
        $filters = $this->resolveIndexFilters($request);

        if ($request->query('export') === 'excel') {
            return $this->exportExcel($filters);
        }

        $userId = (int) $request->user()->id;
        $clinicId = $this->resolveFilterClinicId($request);

        $expenses = $this->listExpensesAction->handle(
            userId: $userId,
            perPage: $filters['per_page'],
            search: $filters['search'],
            status: $filters['status'],
            categoryId: $filters['category_id'],
            clinicId: $filters['clinic_id'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
            paymentMethod: $filters['payment_method'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $categories = $this->listExpenseCategoriesAction->handle($clinicId, true);

        $stats = $this->listExpensesAction->getStats(
            clinicId: $filters['clinic_id'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
            categoryId: $filters['category_id'],
            paymentMethod: $filters['payment_method'],
        );

        $clinics = Clinic::query()
            ->clinical()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Clinic $c) => ['id' => (int) $c->id, 'name' => $c->name])
            ->all();

        $expensesResource = ExpenseResource::collection($expenses);

        if ($request->expectsJson()) {
            return $expensesResource;
        }

        return Inertia::render('expenses/Index', [
            'expenses' => $expensesResource->response()->getData(true),
            'categories' => $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'is_active' => $cat->is_active,
            ])->values()->all(),
            'filters' => $filters,
            'stats' => $stats,
            'clinics' => $clinics,
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse|RedirectResponse
    {
        $userId = (int) $request->user()->id;

        $payload = $request->validated();
        $attachment = $request->file('attachment');

        $expense = $this->createExpenseAction->handle(
            userId: $userId,
            payload: $payload,
            attachment: $attachment,
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
        $expense = Expense::query()
            ->withoutGlobalScope('clinic')
            ->with(['category:id,name', 'user:id,name', 'creator:id,name', 'clinic:id,name'])
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
        $userId = (int) $request->user()->id;

        $payload = $request->validated();
        $attachment = $request->file('attachment');

        $expense = $this->updateExpenseAction->handle(
            userId: $userId,
            expenseId: $expenseId,
            payload: $payload,
            attachment: $attachment,
        );

        if ($request->expectsJson()) {
            return ExpenseResource::make($expense)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث المصروف بنجاح.']);

        return to_route('expenses.index');
    }

    public function destroy(Request $request, int $expenseId): Response|RedirectResponse
    {
        $userId = (int) $request->user()->id;

        $this->deleteExpenseAction->handle(
            userId: $userId,
            expenseId: $expenseId,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف المصروف بنجاح.']);

        return to_route('expenses.index');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $userId = (int) $request->user()->id;

        $deletedIds = [];
        $failedIds = [];

        $ids = array_map('intval', $validated['ids']);

        foreach (array_values(array_unique($ids)) as $expenseId) {
            try {
                $this->deleteExpenseAction->handle(
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
            Inertia::flash('toast', ['type' => 'error', 'message' => 'لم يتم حذف أي مصروف.']);

            return to_route('expenses.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('تم حذف %d مصروف بنجاح.', count($deletedIds)),
        ]);

        return to_route('expenses.index');
    }

    public function updateStatus(Request $request, int $expenseId): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,paid,cancelled'],
        ]);

        $userId = (int) $request->user()->id;

        $expense = $this->approveExpenseAction->handle(
            userId: $userId,
            expenseId: $expenseId,
            status: $validated['status'],
        );

        if ($request->expectsJson()) {
            return ExpenseResource::make($expense)->response();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث حالة المصروف.']);

        return to_route('expenses.index');
    }

    private function exportExcel(array $filters): StreamedResponse
    {
        $clinicId = $filters['clinic_id'];

        return Excel::download(
            new ExpenseExport(
                clinicId: $clinicId,
                status: $filters['status'],
                categoryId: $filters['category_id'],
                dateFrom: $filters['date_from'],
                dateTo: $filters['date_to'],
                paymentMethod: $filters['payment_method'],
                search: $filters['search'],
            ),
            'expenses_'.now()->format('Y-m-d_H-i-s').'.xlsx',
        );
    }

    private function resolveFilterClinicId(Request $request): ?int
    {
        $clinicId = $request->query('clinic_id');

        if ($clinicId !== null && is_numeric($clinicId)) {
            return (int) $clinicId;
        }

        return $request->user()?->clinic_id;
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

        $clinicIdInput = $request->query('clinic_id');
        $clinicId = $clinicIdInput !== null ? (int) $clinicIdInput : ($savedFilters['clinic_id'] ?? null);

        $dateFromInput = $request->query('date_from');
        $dateFrom = $this->normalizeNullableString($dateFromInput ?? ($savedFilters['date_from'] ?? null));

        $dateToInput = $request->query('date_to');
        $dateTo = $this->normalizeNullableString($dateToInput ?? ($savedFilters['date_to'] ?? null));

        $paymentMethodInput = $request->query('payment_method');
        $paymentMethod = $this->normalizeNullableString($paymentMethodInput ?? ($savedFilters['payment_method'] ?? null));

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
            'clinic_id' => $clinicId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payment_method' => $paymentMethod,
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
