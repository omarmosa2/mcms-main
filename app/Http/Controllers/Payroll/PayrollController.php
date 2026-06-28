<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorDeduction;
use App\Models\DoctorDuePayment;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\EmployeeMonthlySalary;
use App\Models\EmployeeSalaryPayment;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
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
        $filters = $this->resolveFilters($request);
        $month = $filters['month'];

        $selectedClinicId = is_int($filters['clinic_id']) ? $filters['clinic_id'] : null;
        $userClinicId = $this->getUserClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request) && $selectedClinicId === null;

        $clinicId = $selectedClinicId ?? $userClinicId;

        if ($includeAllClinics) {
            $this->ensureEmployeeMonthlySalariesAllClinics($month);
            $this->ensureDoctorMonthlyDuesAllClinics($month);
        } else {
            $this->ensureEmployeeMonthlySalaries($clinicId, $month);
            $this->ensureDoctorMonthlyDues($clinicId, $month);
        }

        $employeeRows = $this->employeeRows($clinicId, $month, $filters, $includeAllClinics);
        $doctorRows = $this->doctorRows($clinicId, $month, $filters, $includeAllClinics);

        $payload = [
            'employee_salaries' => $employeeRows->values()->all(),
            'doctor_dues' => $doctorRows->values()->all(),
            'summaries' => $this->summaries($employeeRows, $doctorRows),
            'clinics' => $this->clinicOptions(),
            'filters' => $filters,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('salaries/Index', $payload);
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

    private function authorizePayrollRecordAccess(Request $request, int $recordClinicId): void
    {
        if ($this->canViewAllClinics($request)) {
            return;
        }

        if ($this->getUserClinicId($request) === $recordClinicId) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    public function storeEmployeePayment(Request $request): JsonResponse|RedirectResponse
    {
        $this->resolveClinicId($request);
        $validated = $request->validate([
            'employee_monthly_salary_id' => ['required', Rule::exists('employee_monthly_salaries', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $monthlySalary = EmployeeMonthlySalary::query()
            ->withoutGlobalScope('clinic')
            ->findOrFail((int) $validated['employee_monthly_salary_id']);
        $clinicId = (int) $monthlySalary->clinic_id;

        $this->authorizePayrollRecordAccess($request, $clinicId);

        $remaining = (float) $monthlySalary->remaining_amount;

        if ($this->employeeSalaryHasPayments($monthlySalary)) {
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
            $salaryPayment = EmployeeSalaryPayment::query()->create([
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

            $payment = $this->recordPayrollPayment(
                $salaryPayment,
                $clinicId,
                $request,
                $validated,
                'employee_salary',
            );

            $salaryPayment->update(['payment_id' => $payment->id]);

            $this->refreshMonthlySalaryStatus($monthlySalary);
        });

        return $this->paymentResponse($request, 'تم تسجيل دفعة الراتب بنجاح.');
    }

    public function storeDoctorPayment(Request $request): JsonResponse|RedirectResponse
    {
        $this->resolveClinicId($request);
        $validated = $request->validate([
            'doctor_monthly_due_id' => ['required', Rule::exists('doctor_monthly_dues', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $monthlyDue = DoctorMonthlyDue::query()
            ->withoutGlobalScope('clinic')
            ->findOrFail((int) $validated['doctor_monthly_due_id']);
        $clinicId = (int) $monthlyDue->clinic_id;

        $this->authorizePayrollRecordAccess($request, $clinicId);

        $remaining = (float) $monthlyDue->remaining_amount;

        if ((float) $validated['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ المدفوع لا يمكن أن يتجاوز المبلغ المتبقي.',
            ]);
        }

        DB::transaction(function () use ($monthlyDue, $validated, $request, $clinicId): void {
            $duePayment = DoctorDuePayment::query()->create([
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

            $payment = $this->recordPayrollPayment(
                $duePayment,
                $clinicId,
                $request,
                $validated,
                'doctor_due',
            );

            $duePayment->update(['payment_id' => $payment->id]);

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
            ->withoutClinicScope()
            ->where(function ($query) use ($clinicId): void {
                $query
                    ->where('clinic_id', $clinicId)
                    ->orWhereNull('clinic_id');
            })
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
        $activeDoctors = DoctorProfile::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->get();

        $carbonMonth = CarbonImmutable::createFromFormat('Y-m', $month)?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();
        $periodStart = $carbonMonth->toDateString();
        $periodEnd = $carbonMonth->endOfMonth()->toDateString();

        foreach ($activeDoctors as $doctor) {
            $calculation = $this->calculateDoctorDue($clinicId, $doctor, $periodStart, $periodEnd);

            $record = DoctorMonthlyDue::query()
                ->forClinic($clinicId)
                ->where('doctor_id', $doctor->id)
                ->where('salary_month', $month)
                ->first();

            $paidAmount = $record !== null ? $this->doctorDuePaidAmount($record) : 0.0;
            $remainingAmount = max(0, $calculation['due_amount'] - $paidAmount);

            DoctorMonthlyDue::query()->updateOrCreate([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctor->id,
                'salary_month' => $month,
            ], [
                'clinic_id' => $clinicId,
                'doctor_id' => $doctor->id,
                'salary_month' => $month,
                'payment_type' => $doctor->compensation_type ?? DoctorProfile::COMPENSATION_MONTHLY_FIXED,
                'percentage' => $doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE ? $doctor->compensationAmount() : null,
                'fixed_weekly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED ? $doctor->compensationAmount() : null,
                'fixed_monthly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED ? $doctor->compensationAmount() : null,
                'visits_total_amount' => $calculation['visits_total_amount'],
                'deductions_amount' => $calculation['deductions_amount'],
                'due_amount' => $calculation['due_amount'],
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $this->monthlyDueStatus($calculation['due_amount'], $paidAmount),
            ]);
        }
    }

    private function ensureEmployeeMonthlySalariesAllClinics(string $month): void
    {
        $existingEmployeeIds = EmployeeMonthlySalary::query()
            ->withoutGlobalScope('clinic')
            ->where('salary_month', $month)
            ->pluck('employee_id')
            ->all();

        $activeEmployees = Employee::query()
            ->withoutClinicScope()
            ->where('status', Employee::STATUS_ACTIVE)
            ->when($existingEmployeeIds, fn ($q) => $q->whereNotIn('id', $existingEmployeeIds))
            ->get();

        foreach ($activeEmployees as $employee) {
            $baseSalary = (float) $employee->base_salary;
            $clinicId = (int) ($employee->clinic_id ?? 0);

            if ($clinicId <= 0) {
                continue;
            }

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

    private function ensureDoctorMonthlyDuesAllClinics(string $month): void
    {
        $activeDoctors = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('is_active', true)
            ->get();

        $carbonMonth = CarbonImmutable::createFromFormat('Y-m', $month)?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();
        $periodStart = $carbonMonth->toDateString();
        $periodEnd = $carbonMonth->endOfMonth()->toDateString();

        foreach ($activeDoctors as $doctor) {
            $calculation = $this->calculateDoctorDue((int) $doctor->clinic_id, $doctor, $periodStart, $periodEnd);

            $record = DoctorMonthlyDue::query()
                ->withoutGlobalScope('clinic')
                ->where('clinic_id', (int) $doctor->clinic_id)
                ->where('doctor_id', $doctor->id)
                ->where('salary_month', $month)
                ->first();

            $paidAmount = $record !== null ? $this->doctorDuePaidAmount($record) : 0.0;
            $remainingAmount = max(0, $calculation['due_amount'] - $paidAmount);

            DoctorMonthlyDue::query()->withoutGlobalScope('clinic')->updateOrCreate([
                'clinic_id' => (int) $doctor->clinic_id,
                'doctor_id' => $doctor->id,
                'salary_month' => $month,
            ], [
                'clinic_id' => (int) $doctor->clinic_id,
                'doctor_id' => $doctor->id,
                'salary_month' => $month,
                'payment_type' => $doctor->compensation_type ?? DoctorProfile::COMPENSATION_MONTHLY_FIXED,
                'percentage' => $doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE ? $doctor->compensationAmount() : null,
                'fixed_weekly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED ? $doctor->compensationAmount() : null,
                'fixed_monthly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED ? $doctor->compensationAmount() : null,
                'visits_total_amount' => $calculation['visits_total_amount'],
                'deductions_amount' => $calculation['deductions_amount'],
                'due_amount' => $calculation['due_amount'],
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $this->monthlyDueStatus($calculation['due_amount'], $paidAmount),
            ]);
        }
    }

    /**
     * @return array{visits_total_amount: float, deductions_amount: float, due_amount: float}
     */
    private function calculateDoctorDue(int $clinicId, DoctorProfile $doctor, string $periodStart, string $periodEnd): array
    {
        $compensationValue = (float) ($doctor->compensationAmount() ?? 0);

        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE) {
            $entitlementStats = DoctorAppointmentEntitlement::query()
                ->forClinic($clinicId)
                ->where('doctor_profile_id', $doctor->id)
                ->where('compensation_type', DoctorProfile::COMPENSATION_PERCENTAGE)
                ->whereBetween('appointment_date', [$periodStart, $periodEnd])
                ->selectRaw('COALESCE(SUM(entitlement_amount), 0) as total_entitlement, COALESCE(SUM(appointment_cost), 0) as total_revenue')
                ->first();

            $visitsTotalAmount = (float) ($entitlementStats->total_revenue ?? 0);
            $gross = (float) ($entitlementStats->total_entitlement ?? 0);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED) {
            $visitsTotalAmount = 0.0;
            $gross = $compensationValue;
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED) {
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
    private function employeeRows(int $clinicId, string $month, array $filters, bool $includeAllClinics = false): Collection
    {
        if ($filters['person_type'] === 'doctor') {
            return collect();
        }

        $query = EmployeeMonthlySalary::query()
            ->withoutGlobalScope('clinic')
            ->where('salary_month', $month)
            ->with([
                'employee' => fn ($q) => $q->withoutClinicScope()->select('id', 'clinic_id', 'full_name', 'employee_type', 'job_title'),
                'employee.clinic:id,name',
            ])
            ->withCount([
                'payments' => fn ($query) => $query->withoutGlobalScope('clinic'),
            ]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['employee_type'] !== null, fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('employee_type', $filters['employee_type'])))
            ->when(is_int($filters['clinic_id']), function ($query) use ($filters): void {
                $query->whereHas('employee', function ($employeeQuery) use ($filters): void {
                    $employeeQuery->where('clinic_id', $filters['clinic_id']);
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeMonthlySalary $record): array => [
                'id' => $record->id,
                'employee_monthly_salary_id' => $record->id,
                'employee_id' => $record->employee_id,
                'name' => $record->employee?->full_name ?? 'Employee #'.$record->employee_id,
                'employee_type' => $record->employee?->employee_type,
                'job_title' => $record->employee?->job_title,
                'clinic_id' => $record->employee?->clinic_id,
                'clinic' => $record->employee?->clinic?->name,
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
    private function doctorRows(int $clinicId, string $month, array $filters, bool $includeAllClinics = false): Collection
    {
        if ($filters['person_type'] === 'employee') {
            return collect();
        }

        if ($filters['clinic_id'] === 'unassigned') {
            return collect();
        }

        $carbonMonth = CarbonImmutable::createFromFormat('Y-m', $month)?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();
        $periodStart = $carbonMonth->toDateString();
        $periodEnd = $carbonMonth->endOfMonth()->toDateString();

        $visitQuery = DoctorAppointmentEntitlement::query()
            ->withoutGlobalScope('clinic')
            ->whereBetween('appointment_date', [$periodStart, $periodEnd]);

        if (! $includeAllClinics) {
            $visitQuery->where('clinic_id', $clinicId);
        }

        $visitCounts = $visitQuery
            ->selectRaw('doctor_profile_id, COUNT(*) as visits_count')
            ->groupBy('doctor_profile_id')
            ->pluck('visits_count', 'doctor_profile_id');

        $query = DoctorMonthlyDue::query()
            ->withoutGlobalScope('clinic')
            ->where('salary_month', $month)
            ->with([
                'doctor' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'user_id', 'full_name', 'compensation_type', 'compensation_value', 'percentage_value', 'fixed_weekly_amount', 'fixed_monthly_amount'),
                'doctor.clinic:id,name',
                'doctor.user' => fn ($q) => $q->select('id', 'name'),
            ])
            ->whereHas('doctor', fn ($doctorQuery) => $doctorQuery->withoutGlobalScope('clinic')->where('is_active', true));

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when(is_int($filters['clinic_id']), fn ($query) => $query->whereHas('doctor', fn ($doctorQuery) => $doctorQuery->where('clinic_id', $filters['clinic_id'])))
            ->orderBy('id')
            ->get()
            ->map(fn (DoctorMonthlyDue $record): array => [
                'id' => $record->id,
                'doctor_monthly_due_id' => $record->id,
                'doctor_id' => $record->doctor_id,
                'name' => $record->doctor?->user?->name ?? $record->doctor?->full_name ?? 'Doctor #'.$record->doctor_id,
                'clinic_id' => $record->doctor?->clinic_id,
                'clinic' => $record->doctor?->clinic?->name,
                'payment_type' => $record->payment_type,
                'percentage' => $record->percentage !== null ? (float) $record->percentage : null,
                'fixed_weekly_amount' => $record->fixed_weekly_amount !== null ? (float) $record->fixed_weekly_amount : null,
                'fixed_monthly_amount' => $record->fixed_monthly_amount !== null ? (float) $record->fixed_monthly_amount : null,
                'visits_count' => (int) ($visitCounts[$record->doctor_id] ?? 0),
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
        $totalPaid = $this->employeeSalaryPaidAmount($record);
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
        $totalPaid = $this->doctorDuePaidAmount($record);
        $due = (float) $record->due_amount;
        $remaining = max(0, $due - $totalPaid);

        $record->update([
            'paid_amount' => $totalPaid,
            'remaining_amount' => $remaining,
            'status' => $this->monthlyDueStatus($due, $totalPaid),
        ]);
    }

    private function employeeSalaryHasPayments(EmployeeMonthlySalary $record): bool
    {
        return EmployeeSalaryPayment::query()
            ->withoutGlobalScope('clinic')
            ->where('employee_monthly_salary_id', $record->id)
            ->exists();
    }

    private function employeeSalaryPaidAmount(EmployeeMonthlySalary $record): float
    {
        return (float) EmployeeSalaryPayment::query()
            ->withoutGlobalScope('clinic')
            ->where('employee_monthly_salary_id', $record->id)
            ->sum('amount');
    }

    private function doctorDuePaidAmount(DoctorMonthlyDue $record): float
    {
        return (float) DoctorDuePayment::query()
            ->withoutGlobalScope('clinic')
            ->where('doctor_monthly_due_id', $record->id)
            ->sum('amount');
    }

    private function monthlyDueStatus(float $due, float $paid): string
    {
        if ($paid > 0 && $paid < $due) {
            return DoctorMonthlyDue::STATUS_PARTIALLY_PAID;
        }

        if ($paid >= $due) {
            return DoctorMonthlyDue::STATUS_PAID;
        }

        return DoctorMonthlyDue::STATUS_UNPAID;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function recordPayrollPayment(Model $payable, int $clinicId, Request $request, array $validated, string $referencePrefix): Payment
    {
        return Payment::query()->create([
            'clinic_id' => $clinicId,
            'invoice_id' => null,
            'received_by' => $request->user()?->id,
            'payment_reference' => sprintf('%s-%s', $referencePrefix, $payable->getKey()),
            'method' => $validated['payment_method'] ?? 'cash',
            'status' => Payment::STATUS_RECORDED,
            'amount' => $validated['amount'],
            'refund_amount' => 0,
            'paid_at' => CarbonImmutable::parse((string) $validated['payment_date'])->startOfDay(),
            'refunded_at' => null,
            'notes' => $validated['notes'] ?? null,
            'payable_type' => $payable::class,
            'payable_id' => $payable->getKey(),
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

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'month' => $this->nullableString($request->query('month')) ?? now()->format('Y-m'),
            'person_type' => $this->allowedNullableString($request->query('person_type'), ['employee', 'doctor']),
            'status' => $this->allowedNullableString($request->query('status'), ['unpaid', 'partially_paid', 'paid']),
            'clinic_id' => $this->nullableClinicFilter($request->query('clinic_id')),
            'employee_type' => $this->allowedNullableString($request->query('employee_type'), $this->employeeTypes()),
        ];
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
     * @return array<int, string>
     */
    private function employeeTypes(): array
    {
        return [
            Employee::TYPE_RECEPTION,
            Employee::TYPE_NURSE,
            Employee::TYPE_LAB,
            Employee::TYPE_USER,
            Employee::TYPE_CLEANER,
            Employee::TYPE_GUARD,
            Employee::TYPE_ACCOUNTANT,
            Employee::TYPE_ADMINISTRATIVE,
            Employee::TYPE_OTHER,
        ];
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

    private function nullableClinicFilter(mixed $value): int|string|null
    {
        if ($value === 'unassigned') {
            return 'unassigned';
        }

        return $this->nullableInteger($value);
    }
}
