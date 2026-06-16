<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorDeduction;
use App\Models\DoctorDuePayment;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\EmployeeMonthlySalary;
use App\Models\EmployeeSalaryPayment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);
        $month = $filters['month'];

        $this->ensureEmployeeMonthlySalaries($clinicId, $month);
        $this->ensureDoctorMonthlyDues($clinicId, $month);

        $employeeRows = $this->employeeRows($clinicId, $month, $filters);
        $doctorRows = $this->doctorRows($clinicId, $month, $filters);

        $payload = [
            'employee_salaries' => $employeeRows->values()->all(),
            'doctor_dues' => $doctorRows->values()->all(),
            'summaries' => $this->summaries($employeeRows, $doctorRows),
            'departments' => $this->departmentOptions($clinicId),
            'filters' => $filters,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('salaries/Index', $payload);
    }

    public function storeEmployeePayment(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $validated = $request->validate([
            'employee_monthly_salary_id' => ['required', Rule::exists('employee_monthly_salaries', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $monthlySalary = EmployeeMonthlySalary::query()
            ->forClinic($clinicId)
            ->findOrFail((int) $validated['employee_monthly_salary_id']);

        $remaining = (float) $monthlySalary->remaining_amount;

        if ($monthlySalary->payments()->exists()) {
            throw ValidationException::withMessages([
                'employee_monthly_salary_id' => 'تم تسديد راتب هذا الموظف لهذا الشهر مسبقاً.',
            ]);
        }

        if ((float) $validated['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ المدفوع لا يمكن أن يتجاوز المبلغ المتبقي.',
            ]);
        }

        if (abs((float) $validated['amount'] - $remaining) > 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'يجب تسديد راتب الموظف كاملاً مرة واحدة خلال الشهر.',
            ]);
        }

        DB::transaction(function () use ($monthlySalary, $validated, $request, $clinicId): void {
            EmployeeSalaryPayment::query()->create([
                'clinic_id' => $clinicId,
                'employee_monthly_salary_id' => $monthlySalary->id,
                'employee_id' => $monthlySalary->employee_id,
                'paid_by' => $request->user()?->id,
                'salary_month' => $monthlySalary->salary_month,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_date' => $validated['payment_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->refreshMonthlySalaryStatus($monthlySalary);
        });

        return $this->paymentResponse($request, 'تم تسجيل دفعة الراتب بنجاح.');
    }

    public function storeDoctorPayment(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $validated = $request->validate([
            'doctor_monthly_due_id' => ['required', Rule::exists('doctor_monthly_dues', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $monthlyDue = DoctorMonthlyDue::query()
            ->forClinic($clinicId)
            ->findOrFail((int) $validated['doctor_monthly_due_id']);

        $remaining = (float) $monthlyDue->remaining_amount;

        if ((float) $validated['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ المدفوع لا يمكن أن يتجاوز المبلغ المتبقي.',
            ]);
        }

        DB::transaction(function () use ($monthlyDue, $validated, $request, $clinicId): void {
            DoctorDuePayment::query()->create([
                'clinic_id' => $clinicId,
                'doctor_monthly_due_id' => $monthlyDue->id,
                'doctor_id' => $monthlyDue->doctor_id,
                'paid_by' => $request->user()?->id,
                'salary_month' => $monthlyDue->salary_month,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_date' => $validated['payment_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->refreshMonthlyDueStatus($monthlyDue);
        });

        return $this->paymentResponse($request, 'تم تسجيل دفعة المستحقات بنجاح.');
    }

    private function ensureEmployeeMonthlySalaries(int $clinicId, string $month): void
    {
        $existingEmployeeIds = EmployeeMonthlySalary::query()
            ->forClinic($clinicId)
            ->where('salary_month', $month)
            ->pluck('employee_id')
            ->all();

        $activeEmployees = Employee::query()
            ->forClinic($clinicId)
            ->where('status', Employee::STATUS_ACTIVE)
            ->when($existingEmployeeIds, fn ($q) => $q->whereNotIn('id', $existingEmployeeIds))
            ->get();

        foreach ($activeEmployees as $employee) {
            $baseSalary = (float) $employee->base_salary;

            EmployeeMonthlySalary::query()->create([
                'clinic_id' => $clinicId,
                'employee_id' => $employee->id,
                'salary_month' => $month,
                'base_salary' => $baseSalary,
                'due_amount' => $baseSalary,
                'paid_amount' => 0,
                'remaining_amount' => $baseSalary,
                'status' => EmployeeMonthlySalary::STATUS_UNPAID,
            ]);
        }
    }

    private function ensureDoctorMonthlyDues(int $clinicId, string $month): void
    {
        $existingDoctorIds = DoctorMonthlyDue::query()
            ->forClinic($clinicId)
            ->where('salary_month', $month)
            ->pluck('doctor_id')
            ->all();

        $activeDoctors = DoctorProfile::query()
            ->forClinic($clinicId)
            ->where('status', DoctorProfile::STATUS_ACTIVE)
            ->when($existingDoctorIds, fn ($q) => $q->whereNotIn('id', $existingDoctorIds))
            ->get();

        $carbonMonth = CarbonImmutable::createFromFormat('Y-m', $month)?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();
        $periodStart = $carbonMonth->toDateString();
        $periodEnd = $carbonMonth->endOfMonth()->toDateString();

        foreach ($activeDoctors as $doctor) {
            $calculation = $this->calculateDoctorDue($clinicId, $doctor, $periodStart, $periodEnd, $carbonMonth);

            DoctorMonthlyDue::query()->create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctor->id,
                'salary_month' => $month,
                'payment_type' => $doctor->compensation_type ?? DoctorProfile::COMPENSATION_MONTHLY,
                'percentage' => $doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE ? $doctor->compensation_value : null,
                'fixed_weekly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY ? $doctor->compensation_value : null,
                'fixed_monthly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY ? $doctor->compensation_value : null,
                'visits_total_amount' => $calculation['visits_total_amount'],
                'deductions_amount' => $calculation['deductions_amount'],
                'due_amount' => $calculation['due_amount'],
                'paid_amount' => 0,
                'remaining_amount' => $calculation['due_amount'],
                'status' => $calculation['due_amount'] > 0 ? DoctorMonthlyDue::STATUS_UNPAID : DoctorMonthlyDue::STATUS_PAID,
            ]);
        }
    }

    /**
     * @return array{visits_total_amount: float, deductions_amount: float, due_amount: float}
     */
    private function calculateDoctorDue(int $clinicId, DoctorProfile $doctor, string $periodStart, string $periodEnd, CarbonImmutable $carbonMonth): array
    {
        $compensationValue = (float) ($doctor->compensation_value ?? 0);

        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE) {
            $entitlementStats = DoctorAppointmentEntitlement::query()
                ->forClinic($clinicId)
                ->where('doctor_profile_id', $doctor->id)
                ->whereBetween('appointment_date', [$periodStart, $periodEnd])
                ->selectRaw('COALESCE(SUM(entitlement_amount), 0) as total_entitlement, COALESCE(SUM(appointment_cost), 0) as total_revenue')
                ->first();

            $visitsTotalAmount = (float) ($entitlementStats->total_revenue ?? 0);
            $gross = (float) ($entitlementStats->total_entitlement ?? 0);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY) {
            $visitsTotalAmount = 0.0;
            $weeksInMonth = (int) ceil($carbonMonth->daysInMonth / 7);
            $gross = $compensationValue * $weeksInMonth;
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY) {
            $visitsTotalAmount = 0.0;
            $gross = $compensationValue;
        } else {
            $visitsTotalAmount = 0.0;
            $gross = 0;
        }

        $deductions = (float) DoctorDeduction::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctor->id)
            ->whereBetween('deduction_date', [$periodStart, $periodEnd])
            ->sum('amount');

        $dueAmount = max(0, $gross - $deductions);

        return [
            'visits_total_amount' => $visitsTotalAmount,
            'deductions_amount' => $deductions,
            'due_amount' => $dueAmount,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function employeeRows(int $clinicId, string $month, array $filters): Collection
    {
        if ($filters['person_type'] === 'doctor') {
            return collect();
        }

        return EmployeeMonthlySalary::query()
            ->forClinic($clinicId)
            ->where('salary_month', $month)
            ->with(['employee:id,full_name,employee_type,job_title,department_id', 'employee.department:id,name'])
            ->withCount('payments')
            ->when($filters['department_id'] !== null, function ($query) use ($filters) {
                $query->whereHas('employee', fn ($q) => $q->where('department_id', $filters['department_id']));
            })
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeMonthlySalary $record): array => [
                'id' => $record->id,
                'employee_monthly_salary_id' => $record->id,
                'employee_id' => $record->employee_id,
                'name' => $record->employee?->full_name ?? 'Employee #'.$record->employee_id,
                'employee_type' => $record->employee?->employee_type,
                'job_title' => $record->employee?->job_title,
                'department' => $record->employee?->department?->name,
                'base_salary' => (float) $record->base_salary,
                'salary_month' => $record->salary_month,
                'due_amount' => (float) $record->due_amount,
                'paid_amount' => (float) $record->paid_amount,
                'remaining_amount' => (float) $record->remaining_amount,
                'status' => $record->status,
                'payments_count' => (int) ($record->payments_count ?? 0),
                'can_pay' => $record->status !== EmployeeMonthlySalary::STATUS_PAID && (int) ($record->payments_count ?? 0) === 0,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function doctorRows(int $clinicId, string $month, array $filters): Collection
    {
        if ($filters['person_type'] === 'employee') {
            return collect();
        }

        return DoctorMonthlyDue::query()
            ->forClinic($clinicId)
            ->where('salary_month', $month)
            ->with(['doctor:id,user_id,department_id,compensation_type,compensation_value', 'doctor.user:id,name', 'doctor.department:id,name'])
            ->when($filters['department_id'] !== null, function ($query) use ($filters) {
                $query->whereHas('doctor', fn ($q) => $q->where('department_id', $filters['department_id']));
            })
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('id')
            ->get()
            ->map(fn (DoctorMonthlyDue $record): array => [
                'id' => $record->id,
                'doctor_monthly_due_id' => $record->id,
                'doctor_id' => $record->doctor_id,
                'name' => $record->doctor?->user?->name ?? 'Doctor #'.$record->doctor_id,
                'department' => $record->doctor?->department?->name,
                'payment_type' => $record->payment_type,
                'percentage' => $record->percentage !== null ? (float) $record->percentage : null,
                'fixed_weekly_amount' => $record->fixed_weekly_amount !== null ? (float) $record->fixed_weekly_amount : null,
                'fixed_monthly_amount' => $record->fixed_monthly_amount !== null ? (float) $record->fixed_monthly_amount : null,
                'visits_total_amount' => (float) $record->visits_total_amount,
                'deductions_amount' => (float) $record->deductions_amount,
                'due_amount' => (float) $record->due_amount,
                'paid_amount' => (float) $record->paid_amount,
                'remaining_amount' => (float) $record->remaining_amount,
                'salary_month' => $record->salary_month,
                'status' => $record->status,
            ]);
    }

    private function refreshMonthlySalaryStatus(EmployeeMonthlySalary $record): void
    {
        $totalPaid = (float) $record->payments()->sum('amount');
        $due = (float) $record->due_amount;
        $remaining = max(0, $due - $totalPaid);

        $status = EmployeeMonthlySalary::STATUS_UNPAID;
        if ($totalPaid > 0 && $totalPaid < $due) {
            $status = EmployeeMonthlySalary::STATUS_PARTIALLY_PAID;
        } elseif ($totalPaid >= $due) {
            $status = EmployeeMonthlySalary::STATUS_PAID;
        }

        $record->update([
            'paid_amount' => $totalPaid,
            'remaining_amount' => $remaining,
            'status' => $status,
        ]);
    }

    private function refreshMonthlyDueStatus(DoctorMonthlyDue $record): void
    {
        $totalPaid = (float) $record->payments()->sum('amount');
        $due = (float) $record->due_amount;
        $remaining = max(0, $due - $totalPaid);

        $status = DoctorMonthlyDue::STATUS_UNPAID;
        if ($totalPaid > 0 && $totalPaid < $due) {
            $status = DoctorMonthlyDue::STATUS_PARTIALLY_PAID;
        } elseif ($totalPaid >= $due) {
            $status = DoctorMonthlyDue::STATUS_PAID;
        }

        $record->update([
            'paid_amount' => $totalPaid,
            'remaining_amount' => $remaining,
            'status' => $status,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $employeeRows
     * @param  Collection<int, array<string, mixed>>  $doctorRows
     * @return array<string, float>
     */
    private function summaries(Collection $employeeRows, Collection $doctorRows): array
    {
        $employeeDue = (float) $employeeRows->sum('due_amount');
        $employeePaid = (float) $employeeRows->sum('paid_amount');
        $employeeRemaining = (float) $employeeRows->sum('remaining_amount');
        $doctorDue = (float) $doctorRows->sum('due_amount');
        $doctorPaid = (float) $doctorRows->sum('paid_amount');
        $doctorRemaining = (float) $doctorRows->sum('remaining_amount');

        return [
            'employee_due' => $employeeDue,
            'employee_paid' => $employeePaid,
            'employee_remaining' => $employeeRemaining,
            'employee_count' => $employeeRows->count(),
            'employee_paid_count' => $employeeRows->where('status', EmployeeMonthlySalary::STATUS_PAID)->count(),
            'employee_unpaid_count' => $employeeRows->where('status', EmployeeMonthlySalary::STATUS_UNPAID)->count(),
            'doctor_due' => $doctorDue,
            'doctor_paid' => $doctorPaid,
            'doctor_remaining' => $doctorRemaining,
            'doctor_count' => $doctorRows->count(),
            'doctor_paid_count' => $doctorRows->where('status', DoctorMonthlyDue::STATUS_PAID)->count(),
            'doctor_unpaid_count' => $doctorRows->where('status', DoctorMonthlyDue::STATUS_UNPAID)->count(),
            'total_due' => $employeeDue + $doctorDue,
            'total_paid' => $employeePaid + $doctorPaid,
            'total_remaining' => $employeeRemaining + $doctorRemaining,
            'total_count' => $employeeRows->count() + $doctorRows->count(),
        ];
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
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'month' => $this->nullableString($request->query('month')) ?? now()->format('Y-m'),
            'person_type' => $this->allowedNullableString($request->query('person_type'), ['employee', 'doctor']),
            'status' => $this->allowedNullableString($request->query('status'), ['unpaid', 'partially_paid', 'paid']),
            'department_id' => $this->nullableInteger($request->query('department_id')),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function departmentOptions(int $clinicId): array
    {
        return Department::query()
            ->forClinic($clinicId)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => ['id' => $department->id, 'name' => $department->name])
            ->all();
    }

    private function paymentResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('salaries.index');
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
