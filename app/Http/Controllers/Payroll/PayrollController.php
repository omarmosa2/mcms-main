<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorDeduction;
use App\Models\DoctorMonthlyDue;
use App\Models\DoctorPayment;
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
use Illuminate\Support\Facades\Storage;
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
        $months = $this->resolveDoctorMonths($filters);

        $selectedClinicId = is_int($filters['clinic_id']) ? $filters['clinic_id'] : null;
        $userClinicId = $this->getUserClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request) && $selectedClinicId === null;

        $clinicId = $selectedClinicId ?? $userClinicId;

        if ($includeAllClinics) {
            $this->ensureEmployeeMonthlySalariesAllClinics($filters['month']);
            $this->ensureDoctorMonthlyDuesAllClinics($months);
        } else {
            $this->ensureEmployeeMonthlySalaries($clinicId, $filters['month']);
            $this->ensureDoctorMonthlyDues($clinicId, $months);
        }

        $employeeRows = $this->employeeRows($clinicId, $filters['month'], $filters, $includeAllClinics);
        $doctorRows = $this->doctorRows($clinicId, $months, $filters, $includeAllClinics);

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
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $monthlyDue = DoctorMonthlyDue::query()
            ->withoutGlobalScope('clinic')
            ->findOrFail((int) $validated['doctor_monthly_due_id']);
        $clinicId = (int) $monthlyDue->clinic_id;

        $this->authorizePayrollRecordAccess($request, $clinicId);

        $doctor = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->findOrFail((int) $monthlyDue->doctor_id);

        DB::transaction(function () use ($monthlyDue, $doctor, $validated, $request, $clinicId): void {
            $paymentPeriod = $this->doctorPaymentPeriod($doctor, $monthlyDue, $validated);
            $amount = $this->payableDoctorAmount($clinicId, $doctor, $paymentPeriod['start'], $paymentPeriod['end']);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'doctor_monthly_due_id' => 'لا توجد مستحقات غير مدفوعة لهذا الطبيب ضمن الفترة المحددة.',
                ]);
            }

            if ($paymentPeriod['dedupe_key'] !== null && DoctorPayment::query()
                ->withoutGlobalScope('clinic')
                ->where('doctor_id', $doctor->id)
                ->where('payment_type', $paymentPeriod['type'])
                ->where('dedupe_key', $paymentPeriod['dedupe_key'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'doctor_monthly_due_id' => 'تم تسديد هذه الفترة لهذا الطبيب مسبقاً.',
                ]);
            }

            $doctorPayment = DoctorPayment::query()->create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctor->id,
                'paid_by' => $request->user()?->id,
                'payment_type' => $paymentPeriod['type'],
                'period_start' => $paymentPeriod['start']->toDateString(),
                'period_end' => $paymentPeriod['end']->toDateString(),
                'dedupe_key' => $paymentPeriod['dedupe_key'],
                'amount' => $amount,
                'payment_method' => $validated['payment_method'] ?? null,
                'paid_at' => CarbonImmutable::parse((string) $validated['payment_date'])->startOfDay(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $payment = $this->recordPayrollPayment(
                $doctorPayment,
                $clinicId,
                $request,
                array_merge($validated, [
                    'amount' => $amount,
                    'payment_date' => $validated['payment_date'],
                ]),
                'doctor_payment',
            );

            $doctorPayment->update(['payment_id' => $payment->id]);

            if ($doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE) {
                DoctorAppointmentEntitlement::query()
                    ->forClinic($clinicId)
                    ->where('doctor_profile_id', $doctor->id)
                    ->where('compensation_type', DoctorProfile::COMPENSATION_PERCENTAGE)
                    ->where('status', DoctorAppointmentEntitlement::STATUS_UNPAID)
                    ->whereBetween('appointment_date', [
                        $paymentPeriod['start']->toDateString(),
                        $paymentPeriod['end']->toDateString(),
                    ])
                    ->update(['status' => DoctorAppointmentEntitlement::STATUS_PAID]);
            }

            $this->refreshMonthlyDueStatus($monthlyDue);
        });

        return $this->paymentResponse($request, 'تم تسجيل دفعة المستحقات بنجاح.');
    }

    public function updateBeneficiaryShamCashQr(Request $request, string $type, int $id): JsonResponse
    {
        if (! in_array($type, ['doctor', 'employee'], true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'sham_cash_qr' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($type === 'doctor') {
            $doctor = DoctorProfile::query()
                ->withoutClinicScope()
                ->findOrFail($id);

            $this->authorizePayrollRecordAccess($request, (int) $doctor->clinic_id);

            $oldPath = $doctor->sham_cash_qr_path;
            $newPath = $validated['sham_cash_qr']->store('doctors/sham-cash-qr', 'public');

            $doctor->update(['sham_cash_qr_path' => $newPath]);

            if ($oldPath !== null) {
                Storage::disk('public')->delete($oldPath);
            }

            return response()->json([
                'sham_cash_qr_url' => asset('storage/'.$newPath),
            ]);
        }

        $employee = Employee::query()
            ->withoutClinicScope()
            ->findOrFail($id);

        $this->authorizePayrollRecordAccess($request, (int) $employee->clinic_id);

        $oldPath = $employee->sham_cash_qr_path;
        $newPath = $validated['sham_cash_qr']->store('employees/sham-cash-qr', 'public');

        $employee->update(['sham_cash_qr_path' => $newPath]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'sham_cash_qr_url' => asset('storage/'.$newPath),
        ]);
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

    private function ensureDoctorMonthlyDues(int $clinicId, array $months): void
    {
        $activeDoctors = DoctorProfile::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->get();

        foreach ($months as $month) {
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

    private function ensureDoctorMonthlyDuesAllClinics(array $months): void
    {
        $activeDoctors = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('is_active', true)
            ->get();

        foreach ($months as $month) {
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
                'employee' => fn ($q) => $q->withoutClinicScope()->select('id', 'clinic_id', 'full_name', 'employee_type', 'job_title', 'sham_cash_qr_path'),
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
                'sham_cash_qr_url' => $record->employee?->sham_cash_qr_path !== null
                    ? asset('storage/'.$record->employee->sham_cash_qr_path)
                    : null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function doctorRows(int $clinicId, array $months, array $filters, bool $includeAllClinics = false): Collection
    {
        if ($filters['person_type'] === 'employee') {
            return collect();
        }

        if ($filters['clinic_id'] === 'unassigned') {
            return collect();
        }

        $query = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('is_active', true)
            ->with([
                'clinic:id,name',
                'user' => fn ($q) => $q->select('id', 'name'),
            ]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->when($filters['doctor_payment_type'] !== null, fn ($query) => $query->where('compensation_type', $filters['doctor_payment_type']))
            ->when(is_int($filters['clinic_id']), fn ($query) => $query->where('clinic_id', $filters['clinic_id']))
            ->orderBy('id')
            ->get()
            ->map(function (DoctorProfile $doctor) use ($filters): array {
                $period = $this->doctorDisplayPeriod($doctor, $filters);
                $salaryMonth = $period['start']->format('Y-m');
                $monthlyDue = DoctorMonthlyDue::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', (int) $doctor->clinic_id)
                    ->where('doctor_id', $doctor->id)
                    ->where('salary_month', $salaryMonth)
                    ->first();

                if (! $monthlyDue instanceof DoctorMonthlyDue) {
                    $calculation = $this->calculateDoctorDue(
                        (int) $doctor->clinic_id,
                        $doctor,
                        $period['start']->startOfMonth()->toDateString(),
                        $period['start']->endOfMonth()->toDateString(),
                    );

                    $monthlyDue = DoctorMonthlyDue::query()->withoutGlobalScope('clinic')->create([
                        'clinic_id' => (int) $doctor->clinic_id,
                        'doctor_id' => $doctor->id,
                        'salary_month' => $salaryMonth,
                        'payment_type' => $doctor->compensation_type ?? DoctorProfile::COMPENSATION_MONTHLY_FIXED,
                        'percentage' => $doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE ? $doctor->compensationAmount() : null,
                        'fixed_weekly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED ? $doctor->compensationAmount() : null,
                        'fixed_monthly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED ? $doctor->compensationAmount() : null,
                        'visits_total_amount' => $calculation['visits_total_amount'],
                        'deductions_amount' => $calculation['deductions_amount'],
                        'due_amount' => $calculation['due_amount'],
                        'paid_amount' => 0,
                        'remaining_amount' => $calculation['due_amount'],
                        'status' => $this->monthlyDueStatus($calculation['due_amount'], 0),
                    ]);
                }

                $stats = $this->doctorPeriodStats($doctor, $period['start'], $period['end']);

                return [
                    'id' => $monthlyDue->id,
                    'doctor_monthly_due_id' => $monthlyDue->id,
                    'doctor_id' => $doctor->id,
                    'name' => $doctor->user?->name ?? $doctor->full_name ?? 'Doctor #'.$doctor->id,
                    'clinic_id' => $doctor->clinic_id,
                    'clinic' => $doctor->clinic?->name,
                    'sham_cash_qr_url' => $doctor->sham_cash_qr_path !== null
                        ? asset('storage/'.$doctor->sham_cash_qr_path)
                        : null,
                    'payment_type' => $doctor->compensation_type,
                    'payment_period_type' => $period['type'],
                    'period_start' => $period['start']->toDateString(),
                    'period_end' => $period['end']->toDateString(),
                    'percentage' => $doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE ? (float) $doctor->compensationAmount() : null,
                    'fixed_weekly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED ? (float) $doctor->compensationAmount() : null,
                    'fixed_monthly_amount' => $doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED ? (float) $doctor->compensationAmount() : null,
                    'visits_count' => $stats['visits_count'],
                    'visits_total_amount' => $stats['visits_total_amount'],
                    'deductions_amount' => $stats['deductions_amount'],
                    'due_amount' => $stats['due_amount'],
                    'paid_amount' => $stats['paid_amount'],
                    'remaining_amount' => $stats['remaining_amount'],
                    'salary_month' => $salaryMonth,
                    'status' => $stats['status'],
                ];
            })
            ->when($filters['status'] !== null, fn (Collection $rows) => $rows->where('status', $filters['status'])->values());
    }

    /**
     * @return array{type: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function doctorDisplayPeriod(DoctorProfile $doctor, array $filters): array
    {
        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED) {
            $start = $filters['date_from'] !== null
                ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
                : CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY);
            $end = $filters['date_to'] !== null
                ? CarbonImmutable::parse($filters['date_to'])->startOfDay()
                : $start->addDays(6);

            return ['type' => DoctorPayment::TYPE_WEEKLY, 'start' => $start, 'end' => $end];
        }

        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED) {
            $month = CarbonImmutable::createFromFormat('Y-m', $filters['month'])?->startOfMonth()
                ?? CarbonImmutable::now()->startOfMonth();

            return ['type' => DoctorPayment::TYPE_MONTHLY, 'start' => $month, 'end' => $month->endOfMonth()];
        }

        if ($filters['date_from'] !== null) {
            $start = CarbonImmutable::parse($filters['date_from'])->startOfDay();
            $end = $filters['date_to'] !== null ? CarbonImmutable::parse($filters['date_to'])->startOfDay() : $start;

            return ['type' => DoctorPayment::TYPE_PERCENTAGE, 'start' => $start, 'end' => $end];
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $filters['month'])?->startOfMonth()
            ?? CarbonImmutable::now()->startOfMonth();

        return ['type' => DoctorPayment::TYPE_PERCENTAGE, 'start' => $month, 'end' => $month->endOfMonth()];
    }

    /**
     * @return array{visits_count: int, visits_total_amount: float, deductions_amount: float, due_amount: float, paid_amount: float, remaining_amount: float, status: string}
     */
    private function doctorPeriodStats(DoctorProfile $doctor, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $clinicId = (int) $doctor->clinic_id;
        $visitsCount = 0;
        $visitsTotalAmount = 0.0;
        $dueAmount = 0.0;
        $paidAmount = 0.0;

        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE) {
            $entitlementStats = DoctorAppointmentEntitlement::query()
                ->forClinic($clinicId)
                ->where('doctor_profile_id', $doctor->id)
                ->where('compensation_type', DoctorProfile::COMPENSATION_PERCENTAGE)
                ->whereBetween('appointment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->selectRaw(
                    'COUNT(*) as visits_count, COALESCE(SUM(appointment_cost), 0) as visits_total_amount, COALESCE(SUM(entitlement_amount), 0) as due_amount, COALESCE(SUM(CASE WHEN status = ? THEN entitlement_amount ELSE 0 END), 0) as paid_amount',
                    [DoctorAppointmentEntitlement::STATUS_PAID],
                )
                ->first();

            $visitsCount = (int) ($entitlementStats->visits_count ?? 0);
            $visitsTotalAmount = (float) ($entitlementStats->visits_total_amount ?? 0);
            $dueAmount = (float) ($entitlementStats->due_amount ?? 0);
            $paidAmount = (float) ($entitlementStats->paid_amount ?? 0);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED) {
            $dueAmount = (float) ($doctor->fixed_weekly_amount ?? $doctor->compensation_value ?? 0);
            $paidAmount = $this->doctorPeriodPaidAmount($doctor, DoctorPayment::TYPE_WEEKLY, $periodStart, $periodEnd);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED) {
            $dueAmount = (float) ($doctor->fixed_monthly_amount ?? $doctor->compensation_value ?? 0);
            $paidAmount = $this->doctorPeriodPaidAmount($doctor, DoctorPayment::TYPE_MONTHLY, $periodStart, $periodEnd);
        }

        $deductionsAmount = (float) DoctorDeduction::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctor->id)
            ->whereBetween('deduction_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('amount');

        $dueAfterDeductions = max(0, $dueAmount - $deductionsAmount);
        $remainingAmount = max(0, $dueAfterDeductions - $paidAmount);

        return [
            'visits_count' => $visitsCount,
            'visits_total_amount' => $visitsTotalAmount,
            'deductions_amount' => $deductionsAmount,
            'due_amount' => $dueAfterDeductions,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $this->monthlyDueStatus($dueAfterDeductions, $paidAmount),
        ];
    }

    private function doctorPeriodPaidAmount(DoctorProfile $doctor, string $paymentType, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): float
    {
        return (float) DoctorPayment::query()
            ->withoutGlobalScope('clinic')
            ->where('doctor_id', $doctor->id)
            ->where('payment_type', $paymentType)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->sum('amount');
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
        $doctor = $record->doctor;

        if (! $doctor instanceof DoctorProfile) {
            $doctor = DoctorProfile::query()
                ->withoutGlobalScope('clinic')
                ->find($record->doctor_id);
        }

        if (! $doctor instanceof DoctorProfile) {
            return 0.0;
        }

        $period = $this->doctorPaymentPeriod($doctor, $record);

        return (float) DoctorPayment::query()
            ->withoutGlobalScope('clinic')
            ->where('doctor_id', $record->doctor_id)
            ->where('payment_type', $period['type'])
            ->whereDate('period_start', $period['start']->toDateString())
            ->whereDate('period_end', $period['end']->toDateString())
            ->sum('amount');
    }

    /**
     * @return array{type: string, start: CarbonImmutable, end: CarbonImmutable, dedupe_key: ?string}
     */
    private function doctorPaymentPeriod(DoctorProfile $doctor, DoctorMonthlyDue $monthlyDue, array $validated = []): array
    {
        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED) {
            $start = isset($validated['period_start'])
                ? CarbonImmutable::parse((string) $validated['period_start'])->startOfDay()
                : CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY);
            $end = isset($validated['period_end'])
                ? CarbonImmutable::parse((string) $validated['period_end'])->startOfDay()
                : $start->addDays(6);

            return [
                'type' => DoctorPayment::TYPE_WEEKLY,
                'start' => $start,
                'end' => $end,
                'dedupe_key' => 'weekly:'.$start->toDateString().':'.$end->toDateString(),
            ];
        }

        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED) {
            $start = isset($validated['period_start'])
                ? CarbonImmutable::parse((string) $validated['period_start'])->startOfDay()
                : (CarbonImmutable::createFromFormat('Y-m', $monthlyDue->salary_month)?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth());
            $end = isset($validated['period_end'])
                ? CarbonImmutable::parse((string) $validated['period_end'])->startOfDay()
                : $start->endOfMonth();

            return [
                'type' => DoctorPayment::TYPE_MONTHLY,
                'start' => $start,
                'end' => $end,
                'dedupe_key' => 'monthly:'.$start->toDateString().':'.$end->toDateString(),
            ];
        }

        if (isset($validated['period_start'])) {
            $start = CarbonImmutable::parse((string) $validated['period_start'])->startOfDay();
            $end = isset($validated['period_end']) ? CarbonImmutable::parse((string) $validated['period_end'])->startOfDay() : $start;

            return [
                'type' => DoctorPayment::TYPE_PERCENTAGE,
                'start' => $start,
                'end' => $end,
                'dedupe_key' => null,
            ];
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $monthlyDue->salary_month)?->startOfMonth()
            ?? CarbonImmutable::now()->startOfMonth();

        return [
            'type' => DoctorPayment::TYPE_PERCENTAGE,
            'start' => $month,
            'end' => $month->endOfMonth(),
            'dedupe_key' => null,
        ];
    }

    private function payableDoctorAmount(int $clinicId, DoctorProfile $doctor, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): float
    {
        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE) {
            $gross = (float) DoctorAppointmentEntitlement::query()
                ->forClinic($clinicId)
                ->where('doctor_profile_id', $doctor->id)
                ->where('compensation_type', DoctorProfile::COMPENSATION_PERCENTAGE)
                ->where('status', DoctorAppointmentEntitlement::STATUS_UNPAID)
                ->whereBetween('appointment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->sum('entitlement_amount');
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY_FIXED) {
            $gross = (float) ($doctor->fixed_weekly_amount ?? $doctor->compensation_value ?? 0);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY_FIXED) {
            $gross = (float) ($doctor->fixed_monthly_amount ?? $doctor->compensation_value ?? 0);
        } else {
            $gross = 0.0;
        }

        $deductions = (float) DoctorDeduction::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctor->id)
            ->whereBetween('deduction_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('amount');

        return max(0, $gross - $deductions);
    }

    private function monthlyDueStatus(float $due, float $paid): string
    {
        if ($due <= 0 && $paid <= 0) {
            return DoctorMonthlyDue::STATUS_UNPAID;
        }

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
    private function resolveDoctorMonths(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom !== null) {
            $from = CarbonImmutable::parse($dateFrom)->startOfMonth();
            $to = $dateTo !== null ? CarbonImmutable::parse($dateTo)->startOfMonth() : CarbonImmutable::now()->startOfMonth();

            if ($to->lt($from)) {
                $to = $from;
            }

            $months = [];
            $current = $from;

            while ($current->lte($to)) {
                $months[] = $current->format('Y-m');
                $current = $current->addMonth();
            }

            return $months;
        }

        return [$filters['month']];
    }

    private function resolveFilters(Request $request): array
    {
        return [
            'month' => $this->nullableString($request->query('month')) ?? now()->format('Y-m'),
            'date_from' => $this->nullableString($request->query('date_from')),
            'date_to' => $this->nullableString($request->query('date_to')),
            'person_type' => $this->allowedNullableString($request->query('person_type'), ['employee', 'doctor']),
            'status' => $this->allowedNullableString($request->query('status'), ['unpaid', 'partially_paid', 'paid']),
            'clinic_id' => $this->nullableClinicFilter($request->query('clinic_id')),
            'employee_type' => $this->allowedNullableString($request->query('employee_type'), $this->employeeTypes()),
            'doctor_payment_type' => $this->allowedNullableString($request->query('doctor_payment_type'), [
                DoctorProfile::COMPENSATION_PERCENTAGE,
                DoctorProfile::COMPENSATION_WEEKLY_FIXED,
                DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            ]),
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
