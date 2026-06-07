<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorDeduction;
use App\Models\DoctorProfile;
use App\Models\DoctorSalaryPayment;
use App\Models\Employee;
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
        [$periodStart, $periodEnd] = $this->resolvePeriod($filters);

        $employeeRows = $this->employeeRows($clinicId, $filters);
        $doctorRows = $this->doctorRows($clinicId, $filters, $periodStart, $periodEnd);

        $payload = [
            'employee_salaries' => $employeeRows->values()->all(),
            'doctor_dues' => $doctorRows->values()->all(),
            'summaries' => $this->summaries($employeeRows, $doctorRows),
            'departments' => $this->departmentOptions($clinicId),
            'filters' => [
                ...$filters,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
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
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('clinic_id', $clinicId)],
            'period_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::query()->forClinic($clinicId)->findOrFail((int) $validated['employee_id']);
        $alreadyPaid = (float) EmployeeSalaryPayment::query()
            ->forClinic($clinicId)
            ->where('employee_id', $employee->id)
            ->where('period_month', $validated['period_month'])
            ->sum('amount_paid');
        $amountDue = (float) $employee->base_salary;
        $remaining = max(0, $amountDue - $alreadyPaid);

        if ((float) $validated['amount_paid'] > $remaining) {
            throw ValidationException::withMessages([
                'amount_paid' => 'The paid amount cannot exceed the remaining salary amount.',
            ]);
        }

        EmployeeSalaryPayment::query()->create([
            'clinic_id' => $clinicId,
            'employee_id' => $employee->id,
            'paid_by' => $request->user()?->id,
            'period_month' => $validated['period_month'],
            'amount_due' => $amountDue,
            'amount_paid' => $validated['amount_paid'],
            'payment_method' => $validated['payment_method'] ?? null,
            'paid_at' => $validated['paid_at'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->paymentResponse($request, 'Employee salary payment recorded successfully.');
    }

    public function storeDoctorPayment(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $validated = $request->validate([
            'doctor_profile_id' => ['required', Rule::exists('doctor_profiles', 'id')->where('clinic_id', $clinicId)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $periodStart = CarbonImmutable::parse($validated['period_start'])->startOfDay();
        $periodEnd = CarbonImmutable::parse($validated['period_end'])->endOfDay();
        $doctor = DoctorProfile::query()
            ->forClinic($clinicId)
            ->with(['user:id,name', 'department:id,name'])
            ->findOrFail((int) $validated['doctor_profile_id']);
        $due = $this->doctorDue($clinicId, $doctor, $periodStart, $periodEnd);
        $alreadyPaid = (float) DoctorSalaryPayment::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctor->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->sum('amount_paid');
        $remaining = max(0, $due['amount_due'] - $alreadyPaid);

        if ((float) $validated['amount_paid'] > $remaining) {
            throw ValidationException::withMessages([
                'amount_paid' => 'The paid amount cannot exceed the remaining doctor due.',
            ]);
        }

        DoctorSalaryPayment::query()->create([
            'clinic_id' => $clinicId,
            'doctor_profile_id' => $doctor->id,
            'paid_by' => $request->user()?->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'amount_due' => $due['amount_due'],
            'amount_paid' => $validated['amount_paid'],
            'payment_method' => $validated['payment_method'] ?? null,
            'paid_at' => $validated['paid_at'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->paymentResponse($request, 'Doctor due payment recorded successfully.');
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
            'date_from' => $this->nullableString($request->query('date_from')),
            'date_to' => $this->nullableString($request->query('date_to')),
            'person_type' => $this->allowedNullableString($request->query('person_type'), ['employee', 'doctor']),
            'status' => $this->allowedNullableString($request->query('status'), ['unpaid', 'partially_paid', 'paid']),
            'department_id' => $this->nullableInteger($request->query('department_id')),
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
    private function employeeRows(int $clinicId, array $filters): Collection
    {
        if ($filters['person_type'] === 'doctor') {
            return collect();
        }

        $month = $filters['month'];
        $payments = EmployeeSalaryPayment::query()
            ->forClinic($clinicId)
            ->where('period_month', $month)
            ->select('employee_id', DB::raw('SUM(amount_paid) as paid_amount'), DB::raw('MAX(paid_at) as last_paid_at'))
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        return Employee::query()
            ->forClinic($clinicId)
            ->with('department:id,name')
            ->when($filters['department_id'] !== null, fn ($query) => $query->where('department_id', $filters['department_id']))
            ->orderBy('full_name')
            ->get()
            ->map(function (Employee $employee) use ($payments, $month): array {
                $payment = $payments->get($employee->id);
                $amountDue = (float) $employee->base_salary;
                $amountPaid = (float) ($payment->paid_amount ?? 0);
                $remaining = max(0, $amountDue - $amountPaid);

                return [
                    'id' => $employee->id,
                    'employee_id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_type' => $employee->employee_type,
                    'job_title' => $employee->job_title,
                    'department' => $employee->department?->name,
                    'base_salary' => $amountDue,
                    'period_month' => $month,
                    'status' => $this->paymentStatus($amountDue, $amountPaid),
                    'amount_paid' => $amountPaid,
                    'amount_remaining' => $remaining,
                    'paid_at' => $payment->last_paid_at ?? null,
                ];
            })
            ->filter(fn (array $row): bool => $this->matchesStatus($row, $filters['status']))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function doctorRows(int $clinicId, array $filters, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Collection
    {
        if ($filters['person_type'] === 'employee') {
            return collect();
        }

        return DoctorProfile::query()
            ->forClinic($clinicId)
            ->with(['user:id,name', 'department:id,name'])
            ->when($filters['department_id'] !== null, fn ($query) => $query->where('department_id', $filters['department_id']))
            ->orderBy('id')
            ->get()
            ->map(function (DoctorProfile $doctor) use ($clinicId, $periodStart, $periodEnd): array {
                $due = $this->doctorDue($clinicId, $doctor, $periodStart, $periodEnd);
                $paid = (float) DoctorSalaryPayment::query()
                    ->forClinic($clinicId)
                    ->where('doctor_profile_id', $doctor->id)
                    ->whereDate('period_start', $periodStart->toDateString())
                    ->whereDate('period_end', $periodEnd->toDateString())
                    ->sum('amount_paid');

                return [
                    ...$due,
                    'id' => $doctor->id,
                    'doctor_profile_id' => $doctor->id,
                    'name' => $doctor->user?->name ?? 'Doctor #'.$doctor->id,
                    'department' => $doctor->department?->name,
                    'compensation_type' => $doctor->compensation_type,
                    'compensation_value' => (float) ($doctor->compensation_value ?? 0),
                    'amount_paid' => $paid,
                    'amount_remaining' => max(0, $due['amount_due'] - $paid),
                    'status' => $this->paymentStatus($due['amount_due'], $paid),
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ];
            })
            ->filter(fn (array $row): bool => $this->matchesStatus($row, $filters['status']))
            ->values();
    }

    /**
     * @return array<string, float|int>
     */
    private function doctorDue(int $clinicId, DoctorProfile $doctor, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $compensationValue = (float) ($doctor->compensation_value ?? 0);

        if ($doctor->compensation_type === DoctorProfile::COMPENSATION_PERCENTAGE) {
            $entitlementStats = DoctorAppointmentEntitlement::query()
                ->forClinic($clinicId)
                ->where('doctor_profile_id', $doctor->id)
                ->whereBetween('appointment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->selectRaw('COUNT(*) as appointments_count, COALESCE(SUM(entitlement_amount), 0) as total_entitlement, COALESCE(SUM(appointment_cost), 0) as total_revenue')
                ->first();

            $visitCount = (int) ($entitlementStats->appointments_count ?? 0);
            $revenue = (float) ($entitlementStats->total_revenue ?? 0);
            $gross = (float) ($entitlementStats->total_entitlement ?? 0);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_WEEKLY) {
            $visitCount = 0;
            $revenue = 0.0;
            $gross = $compensationValue * (int) ceil(($periodStart->diffInDays($periodEnd) + 1) / 7);
        } elseif ($doctor->compensation_type === DoctorProfile::COMPENSATION_MONTHLY) {
            $visitCount = 0;
            $revenue = 0.0;
            $gross = $compensationValue;
        } else {
            $visitCount = 0;
            $revenue = 0.0;
            $gross = 0;
        }

        $deductions = (float) DoctorDeduction::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctor->id)
            ->whereBetween('deduction_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('amount');

        return [
            'visits_count' => $visitCount,
            'consultation_revenue' => $revenue,
            'procedure_revenue' => 0.0,
            'deductions' => $deductions,
            'amount_due' => max(0, $gross - $deductions),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $employeeRows
     * @param  Collection<int, array<string, mixed>>  $doctorRows
     * @return array<string, float>
     */
    private function summaries(Collection $employeeRows, Collection $doctorRows): array
    {
        $employeeDue = (float) $employeeRows->sum('base_salary');
        $employeePaid = (float) $employeeRows->sum('amount_paid');
        $doctorDue = (float) $doctorRows->sum('amount_due');
        $doctorPaid = (float) $doctorRows->sum('amount_paid');

        return [
            'employee_due' => $employeeDue,
            'employee_paid' => $employeePaid,
            'doctor_due' => $doctorDue,
            'doctor_paid' => $doctorPaid,
            'remaining' => max(0, ($employeeDue + $doctorDue) - ($employeePaid + $doctorPaid)),
            'total_monthly_payroll' => $employeePaid + $doctorPaid,
        ];
    }

    private function paymentStatus(float $due, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $due ? 'paid' : 'partially_paid';
    }

    private function matchesStatus(array $row, ?string $status): bool
    {
        return $status === null || $row['status'] === $status;
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
