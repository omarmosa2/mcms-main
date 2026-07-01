<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorPayment;
use App\Models\DoctorProfile;
use App\Models\EmployeeMonthlySalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\Cache\CacheService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class FinancialController extends Controller
{
    public function __construct(private CacheService $cacheService) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = $this->resolveFilters($request);
        [$periodStart, $periodEnd] = $this->resolvePeriod($filters);

        $selectedClinicId = $this->nullableInteger($filters['clinic_id'] ?? null);
        $userClinicId = $this->getUserClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request) && $selectedClinicId === null;

        $clinicId = $selectedClinicId ?? $userClinicId;

        $clinic = Clinic::query()->find($clinicId);
        $clinicName = $clinic?->name ?? '-';

        $transactionType = $filters['transaction_type'];

        $incomeRows = collect();
        $expenseRows = collect();

        if ($transactionType === null || $transactionType === 'income') {
            $incomeRows = $this->appointmentFinancialRows($clinicId, $clinicName, $filters, $periodStart, $periodEnd, $includeAllClinics);
        }

        if ($transactionType === null || $transactionType === 'expense') {
            $expenseRows = $this->expenseRows($clinicId, $filters, $periodStart, $periodEnd, $includeAllClinics);
        }

        $doctorEntitlements = $this->doctorEntitlementRows($clinicId, $periodStart, $periodEnd, $includeAllClinics);
        $employeeSalaryRows = $this->employeeSalaryRows($clinicId, $filters['month'], $includeAllClinics);

        $summaries = $this->computeSummaries($incomeRows, $expenseRows, $doctorEntitlements, $employeeSalaryRows, $clinicId, $periodStart, $periodEnd, $includeAllClinics);

        $chartData = $this->chartData($clinicId, $periodStart, $periodEnd, $includeAllClinics, $filters['month']);

        $payload = [
            'financial_rows' => $incomeRows->values()->all(),
            'expense_rows' => $expenseRows->values()->all(),
            'doctor_entitlements' => $doctorEntitlements->values()->all(),
            'employee_salaries' => $employeeSalaryRows->values()->all(),
            'summaries' => $summaries,
            'chart_data' => $chartData,
            'filters' => [
                ...$filters,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'clinics' => $this->clinicOptions(),
            'doctors' => $this->doctorsDropdown($clinicId, $includeAllClinics),
            'patients' => $this->patientsDropdown($clinicId, $includeAllClinics),
            'expense_categories' => $this->expenseCategoriesDropdown($clinicId, $includeAllClinics),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('financial/Index', $payload);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,card,other'],
            'status' => ['required', 'string', 'in:pending,paid,cancelled'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $expense = Expense::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $request->user()?->id,
            'created_by' => $request->user()?->id,
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'],
            'status' => $validated['status'],
            'paid_to' => $validated['paid_to'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
        ]);

        return response()->json(['data' => $expense, 'message' => 'تم تسجيل المصروف بنجاح.'], Response::HTTP_CREATED);
    }

    public function updateExpense(Request $request, int $expenseId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $expense = Expense::query()->forClinic($clinicId)->findOrFail($expenseId);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,card,other'],
            'status' => ['required', 'string', 'in:pending,paid,cancelled'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $expense->update([
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'],
            'status' => $validated['status'],
            'paid_to' => $validated['paid_to'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $expense, 'message' => 'تم تعديل المصروف بنجاح.']);
    }

    public function destroyExpense(Request $request, int $expenseId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $expense = Expense::query()->forClinic($clinicId)->findOrFail($expenseId);
        $expense->delete();

        return response()->json(['message' => 'تم حذف المصروف بنجاح.']);
    }

    private function getUserClinicId(Request $request): ?int
    {
        $clinicId = $request->user()?->clinic_id;

        return $clinicId !== null ? (int) $clinicId : null;
    }

    private function canViewAllClinics(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('clinic_admin'));
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $this->getUserClinicId($request);

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return $clinicId;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'month' => $this->nullableString($request->query('month')) ?? now()->format('Y-m'),
            'date_from' => $this->nullableString($request->query('date_from')),
            'date_to' => $this->nullableString($request->query('date_to')),
            'status' => $this->allowedNullableString($request->query('status'), ['unpaid', 'partially_paid', 'paid']),
            'clinic_id' => $this->nullableInteger($request->query('clinic_id')),
            'doctor_id' => $this->nullableInteger($request->query('doctor_id')),
            'patient_id' => $this->nullableInteger($request->query('patient_id')),
            'appointment_type' => $this->allowedNullableString($request->query('appointment_type'), ['first_visit', 'review']),
            'payment_method' => $this->allowedNullableString($request->query('payment_method'), ['cash', 'card', 'bank_transfer', 'insurance', 'online']),
            'transaction_type' => $this->allowedNullableString($request->query('transaction_type'), ['income', 'expense']),
            'expense_category_id' => $this->nullableInteger($request->query('expense_category_id')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(array $filters): array
    {
        if ($filters['date_from'] !== null && $filters['date_to'] !== null) {
            return [
                CarbonImmutable::parse($filters['date_from'])->startOfDay(),
                CarbonImmutable::parse($filters['date_to'])->endOfDay(),
            ];
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $filters['month']) ?: CarbonImmutable::now();

        return [$month->startOfMonth(), $month->endOfMonth()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function appointmentFinancialRows(int $clinicId, string $clinicName, array $filters, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics = false): Collection
    {
        $query = Appointment::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed()
            ->with([
                'clinic:id,name',
                'patient' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'first_name', 'last_name', 'file_number'),
                'doctor' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'name'),
                'creator:id,name',
            ])
            ->whereBetween('scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        if ($filters['doctor_id'] !== null) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if ($filters['patient_id'] !== null) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if ($filters['appointment_type'] !== null) {
            $query->where('appointment_type', $filters['appointment_type']);
        }

        $appointments = $query->orderBy('scheduled_for', 'desc')->get();

        $paymentData = $this->paymentDataForAppointments($appointments->pluck('id')->all());

        $rows = $appointments->map(function (Appointment $appointment) use ($clinicName, $paymentData): array {
            $appointmentId = $appointment->id;
            $paidAmount = (float) ($paymentData[$appointmentId]['paid'] ?? 0);
            $cost = (float) ($appointment->cost ?? 0);
            $remaining = max(0, $cost - $paidAmount);
            $lastPaymentMethod = $paymentData[$appointmentId]['last_method'] ?? null;

            return [
                'appointment_id' => $appointmentId,
                'clinic_name' => $appointment->clinic?->name ?? $clinicName,
                'patient_name' => trim(($appointment->patient?->first_name ?? '').' '.($appointment->patient?->last_name ?? '')),
                'file_number' => $appointment->patient?->file_number,
                'doctor_name' => $appointment->doctor?->name ?? '-',
                'appointment_type' => $appointment->appointment_type ?? 'first_visit',
                'cost' => $cost,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'payment_status' => $this->paymentStatus($cost, $paidAmount),
                'appointment_date' => $appointment->scheduled_for?->toDateString(),
                'payment_method' => $lastPaymentMethod,
                'created_by_name' => $appointment->creator?->name ?? '-',
            ];
        });

        $rows = $rows->filter(function (array $row) use ($filters): bool {
            if ($filters['status'] !== null && $row['payment_status'] !== $filters['status']) {
                return false;
            }

            if ($filters['payment_method'] !== null && $row['payment_method'] !== $filters['payment_method']) {
                return false;
            }

            return true;
        })->values();

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function expenseRows(int $clinicId, array $filters, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics = false): Collection
    {
        $query = Expense::query()
            ->withoutGlobalScope('clinic')
            ->where('status', Expense::STATUS_PAID)
            ->with([
                'category:id,name',
                'user:id,name',
                'clinic:id,name',
            ]);

        if ($filters['date_from'] !== null && $filters['date_to'] !== null) {
            $query->whereBetween('expense_date', [$filters['date_from'], $filters['date_to']]);
        } elseif ($filters['month'] !== null) {
            $query->where('expense_date', 'like', $filters['month'].'%');
        }

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        if ($filters['expense_category_id'] !== null) {
            $query->where('category_id', $filters['expense_category_id']);
        }

        if ($filters['payment_method'] !== null) {
            $query->where('payment_method', $filters['payment_method']);
        }

        return $query
            ->orderBy('expense_date', 'desc')
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date?->toDateString(),
                'category_name' => $expense->category?->name ?? '-',
                'description' => $expense->title ?? $expense->description,
                'amount' => (float) $expense->amount,
                'payment_method' => $expense->payment_method,
                'user_name' => $expense->creator?->name ?? ($expense->user?->name ?? '-'),
                'clinic_name' => $expense->clinic?->name ?? '-',
                'notes' => $expense->description,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function doctorEntitlementRows(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics = false): Collection
    {
        $month = $periodStart->format('Y-m');

        $query = DoctorMonthlyDue::query()
            ->withoutGlobalScope('clinic')
            ->where('salary_month', $month)
            ->with([
                'doctor' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'user_id', 'full_name', 'compensation_type', 'compensation_value', 'percentage_value', 'fixed_weekly_amount', 'fixed_monthly_amount'),
                'doctor.clinic:id,name',
                'doctor.user' => fn ($q) => $q->select('id', 'name'),
            ])
            ->whereHas('doctor', fn ($q) => $q->withoutGlobalScope('clinic')->where('is_active', true));

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (DoctorMonthlyDue $record): array => [
                'id' => $record->id,
                'doctor_name' => $record->doctor?->user?->name ?? $record->doctor?->full_name ?? 'Doctor #'.$record->doctor_id,
                'clinic_name' => $record->doctor?->clinic?->name ?? '-',
                'payment_type' => $record->payment_type,
                'due_amount' => (float) $record->due_amount,
                'paid_amount' => (float) $record->paid_amount,
                'remaining_amount' => (float) $record->remaining_amount,
                'status' => $record->status,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function employeeSalaryRows(int $clinicId, string $month, bool $includeAllClinics = false): Collection
    {
        $query = EmployeeMonthlySalary::query()
            ->withoutGlobalScope('clinic')
            ->where('salary_month', $month)
            ->with([
                'employee' => fn ($q) => $q->withoutClinicScope()->select('id', 'clinic_id', 'full_name', 'employee_type'),
            ]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeMonthlySalary $record): array => [
                'id' => $record->id,
                'employee_name' => $record->employee?->full_name ?? 'Employee #'.$record->employee_id,
                'base_salary' => (float) $record->base_salary,
                'due_amount' => (float) $record->due_amount,
                'paid_amount' => (float) $record->paid_amount,
                'remaining_amount' => (float) $record->remaining_amount,
                'status' => $record->status,
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $incomeRows
     * @param  Collection<int, array<string, mixed>>  $expenseRows
     * @param  Collection<int, array<string, mixed>>  $doctorEntitlements
     * @param  Collection<int, array<string, mixed>>  $employeeSalaries
     * @return array<string, float|int>
     */
    private function computeSummaries(
        Collection $incomeRows,
        Collection $expenseRows,
        Collection $doctorEntitlements,
        Collection $employeeSalaries,
        int $clinicId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        bool $includeAllClinics,
    ): array {
        $totalIncome = (float) $incomeRows->sum('cost');
        $totalCollected = (float) $incomeRows->sum('paid_amount');
        $totalRemaining = (float) $incomeRows->sum('remaining_amount');

        $doctorDue = (float) $doctorEntitlements->sum('due_amount');
        $doctorPaid = $this->doctorSalaryPaidAmount($clinicId, $periodStart, $periodEnd, $includeAllClinics);

        $employeeDue = (float) $employeeSalaries->sum('due_amount');
        $employeePaid = (float) $employeeSalaries->sum('paid_amount');

        $totalExpenses = (float) $expenseRows->sum('amount');

        $totalOutflow = $doctorDue + $employeeDue + $totalExpenses;
        $netProfit = $totalIncome - $totalOutflow;

        $totalActuallyPaid = $doctorPaid + $employeePaid + $totalExpenses;
        $netLiquidity = $totalCollected - $totalActuallyPaid;

        return [
            'total_income' => $totalIncome,
            'total_collected' => $totalCollected,
            'total_remaining' => $totalRemaining,
            'doctor_due' => $doctorDue,
            'doctor_paid' => $doctorPaid,
            'employee_salaries' => $employeeDue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_liquidity' => $netLiquidity,
            'total_outflow' => $totalOutflow,
            'paid_count' => $incomeRows->where('payment_status', 'paid')->count(),
            'unpaid_count' => $incomeRows->where('payment_status', 'unpaid')->count(),
            'partially_paid_count' => $incomeRows->where('payment_status', 'partially_paid')->count(),
        ];
    }

    private function doctorSalaryPaidAmount(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics): float
    {
        $query = DoctorPayment::query()
            ->withoutGlobalScope('clinic')
            ->whereBetween('paid_at', [$periodStart, $periodEnd]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @return array<string, mixed>
     */
    private function chartData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics, string $month): array
    {
        $dailyIncome = $this->dailyIncomeData($clinicId, $periodStart, $periodEnd, $includeAllClinics);
        $incomeByClinic = $this->incomeByClinicData($periodStart, $periodEnd);
        $incomeByDoctor = $this->incomeByDoctorData($clinicId, $periodStart, $periodEnd, $includeAllClinics);
        $expensesByCategory = $this->expensesByCategoryData($clinicId, $periodStart, $periodEnd, $includeAllClinics);
        $monthlyProfit = $this->monthlyProfitData($clinicId, $periodStart, $includeAllClinics);

        return [
            'daily_income' => $dailyIncome,
            'income_by_clinic' => $incomeByClinic,
            'income_by_doctor' => $incomeByDoctor,
            'expenses_by_category' => $expensesByCategory,
            'monthly_profit' => $monthlyProfit,
        ];
    }

    /**
     * @return array<int, array{date: string, amount: float}>
     */
    private function dailyIncomeData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics): array
    {
        $query = Appointment::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed()
            ->whereBetween('scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        $rows = $query
            ->selectRaw('DATE(scheduled_for) as day, COALESCE(SUM(cost), 0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($row) => ['date' => $row->day, 'amount' => (float) $row->total])->all();
    }

    /**
     * @return array<int, array{clinic_name: string, amount: float}>
     */
    private function incomeByClinicData(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Appointment::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed()
            ->join('clinics', 'clinics.id', '=', 'appointments.clinic_id')
            ->whereBetween('appointments.scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
            ->selectRaw('clinics.name as clinic_name, COALESCE(SUM(appointments.cost), 0) as total')
            ->groupBy('clinics.id', 'clinics.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['clinic_name' => $row->clinic_name, 'amount' => (float) $row->total])
            ->all();
    }

    /**
     * @return array<int, array{doctor_name: string, amount: float}>
     */
    private function incomeByDoctorData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics): array
    {
        $query = Appointment::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed()
            ->join('users', 'users.id', '=', 'appointments.doctor_id')
            ->whereBetween('appointments.scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);

        if (! $includeAllClinics) {
            $query->where('appointments.clinic_id', $clinicId);
        }

        return $query
            ->selectRaw('users.name as doctor_name, COALESCE(SUM(appointments.cost), 0) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['doctor_name' => $row->doctor_name, 'amount' => (float) $row->total])
            ->all();
    }

    /**
     * @return array<int, array{category_name: string, amount: float}>
     */
    private function expensesByCategoryData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics): array
    {
        $query = Expense::query()
            ->withoutGlobalScope('clinic')
            ->where('status', Expense::STATUS_PAID)
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->whereBetween('expenses.expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        if (! $includeAllClinics) {
            $query->where('expenses.clinic_id', $clinicId);
        }

        return $query
            ->selectRaw('COALESCE(expense_categories.name, \'بدون تصنيف\') as category_name, COALESCE(SUM(expenses.amount), 0) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['category_name' => $row->category_name, 'amount' => (float) $row->total])
            ->all();
    }

    /**
     * @return array<int, array{month: string, income: float, outflow: float, profit: float}>
     */
    private function monthlyProfitData(int $clinicId, CarbonImmutable $periodStart, bool $includeAllClinics): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $periodStart->subMonths($i);
            $months[] = $m->format('Y-m');
        }

        $result = [];

        foreach ($months as $m) {
            $mStart = CarbonImmutable::createFromFormat('Y-m', $m)->startOfMonth();
            $mEnd = $mStart->endOfMonth();

            $incomeQuery = Appointment::query()
                ->withoutGlobalScope('clinic')
                ->withoutTrashed()
                ->whereBetween('scheduled_for', [$mStart, $mEnd])
                ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);

            if (! $includeAllClinics) {
                $incomeQuery->where('clinic_id', $clinicId);
            }

            $income = (float) $incomeQuery->sum('cost');

            $expenseQuery = Expense::query()
                ->withoutGlobalScope('clinic')
                ->where('status', Expense::STATUS_PAID)
                ->whereBetween('expense_date', [$mStart->toDateString(), $mEnd->toDateString()]);

            if (! $includeAllClinics) {
                $expenseQuery->where('clinic_id', $clinicId);
            }

            $expenses = (float) $expenseQuery->sum('amount');

            $doctorDueQuery = DoctorMonthlyDue::query()
                ->withoutGlobalScope('clinic')
                ->where('salary_month', $m);

            if (! $includeAllClinics) {
                $doctorDueQuery->where('clinic_id', $clinicId);
            }

            $doctorDue = (float) $doctorDueQuery->sum('due_amount');

            $employeeDueQuery = EmployeeMonthlySalary::query()
                ->withoutGlobalScope('clinic')
                ->where('salary_month', $m);

            if (! $includeAllClinics) {
                $employeeDueQuery->where('clinic_id', $clinicId);
            }

            $employeeDue = (float) $employeeDueQuery->sum('due_amount');

            $outflow = $expenses + $doctorDue + $employeeDue;

            $result[] = [
                'month' => $m,
                'income' => $income,
                'outflow' => $outflow,
                'profit' => $income - $outflow,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int>  $appointmentIds
     * @return array<int, array{paid: float, last_method: string|null}>
     */
    private function paymentDataForAppointments(array $appointmentIds): array
    {
        if (empty($appointmentIds)) {
            return [];
        }

        $invoicePayments = Invoice::query()
            ->withoutGlobalScope('clinic')
            ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.appointment_id', $appointmentIds)
            ->whereNotIn('payments.status', Payment::TERMINAL_STATUSES)
            ->select(
                'invoices.appointment_id',
                DB::raw('SUM(payments.amount - payments.refund_amount) as total_paid'),
                DB::raw('MAX(payments.method) as last_method'),
            )
            ->groupBy('invoices.appointment_id')
            ->get()
            ->keyBy('appointment_id');

        $result = [];

        foreach ($appointmentIds as $id) {
            $record = $invoicePayments->get($id);
            $result[$id] = [
                'paid' => $record ? (float) $record->total_paid : 0,
                'last_method' => $record?->last_method,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function doctorsDropdown(int $clinicId, bool $includeAllClinics = false): array
    {
        $query = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('doctor_profiles.is_active', true)
            ->join('users', 'users.id', '=', 'doctor_profiles.user_id')
            ->select('users.id', 'users.name');

        if (! $includeAllClinics) {
            $query->where('doctor_profiles.clinic_id', $clinicId);
        }

        return $query
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, full_name: string}>
     */
    private function patientsDropdown(int $clinicId, bool $includeAllClinics = false): array
    {
        $query = Patient::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed();

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"))
            ->orderBy('first_name')
            ->limit(500)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'full_name' => $row->full_name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function clinicOptions(): array
    {
        return Clinic::query()
            ->clinical()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Clinic $clinic): array => [
                'id' => (int) $clinic->id,
                'name' => $clinic->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function expenseCategoriesDropdown(int $clinicId, bool $includeAllClinics = false): array
    {
        $query = ExpenseCategory::query()
            ->withoutGlobalScope('clinic')
            ->where('is_active', true);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ExpenseCategory $cat): array => ['id' => (int) $cat->id, 'name' => $cat->name])
            ->all();
    }

    private function paymentStatus(float $cost, float $paid): string
    {
        if ($cost <= 0) {
            return 'unpaid';
        }

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid >= $cost) {
            return 'paid';
        }

        return 'partially_paid';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedNullableString(mixed $value, array $allowed): ?string
    {
        $value = $this->nullableString($value);

        return $value !== null && in_array($value, $allowed, true) ? $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
