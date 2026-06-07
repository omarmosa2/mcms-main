<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);

        $employees = Employee::query()
            ->forClinic($clinicId)
            ->with('department:id,name,code')
            ->withCount('salaryPayments')
            ->when($filters['search'] !== null, function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%");
                });
            })
            ->when($filters['employee_type'] !== null, fn ($query) => $query->where('employee_type', $filters['employee_type']))
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['department_id'] !== null, fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['education_level'] !== null, fn ($query) => $query->where('education_level', $filters['education_level']))
            ->when($filters['hire_date_from'] !== null, fn ($query) => $query->whereDate('hire_date', '>=', $filters['hire_date_from']))
            ->when($filters['hire_date_to'] !== null, fn ($query) => $query->whereDate('hire_date', '<=', $filters['hire_date_to']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $payload = [
            'employees' => $employees->through(fn (Employee $employee): array => $this->employeePayload($employee))->toArray(),
            'departments' => $this->departmentOptions($clinicId),
            'filters' => $filters,
            'stats' => [
                'total' => Employee::query()->forClinic($clinicId)->count(),
                'active' => Employee::query()->forClinic($clinicId)->where('status', Employee::STATUS_ACTIVE)->count(),
                'inactive' => Employee::query()->forClinic($clinicId)->where('status', Employee::STATUS_INACTIVE)->count(),
                'monthly_salaries' => (float) Employee::query()->forClinic($clinicId)->where('status', Employee::STATUS_ACTIVE)->sum('base_salary'),
            ],
            'options' => [
                'employee_types' => $this->employeeTypes(),
                'education_levels' => $this->educationLevels(),
                'statuses' => [Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE],
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('employees/Index', $payload);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $this->validatedPayload($request, $clinicId);

        $employee = Employee::query()->create([
            ...$payload,
            'clinic_id' => $clinicId,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->employeePayload($employee->load('department'))], Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee saved successfully.']);

        return to_route('employees.index');
    }

    public function update(Request $request, int $employeeId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $employee = Employee::query()->forClinic($clinicId)->findOrFail($employeeId);
        $payload = $this->validatedPayload($request, $clinicId, $employee->id);

        $employee->update($payload);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->employeePayload($employee->refresh()->load('department'))]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee updated successfully.']);

        return to_route('employees.index');
    }

    public function destroy(Request $request, int $employeeId): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $employee = Employee::query()->forClinic($clinicId)->withCount('salaryPayments')->findOrFail($employeeId);

        DB::transaction(function () use ($employee): void {
            if ($employee->salary_payments_count > 0) {
                $employee->forceFill(['status' => Employee::STATUS_INACTIVE])->save();

                return;
            }

            $employee->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(null, Response::HTTP_NO_CONTENT);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee archived successfully.']);

        return to_route('employees.index');
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
            'search' => $this->nullableString($request->query('search')),
            'employee_type' => $this->allowedNullableString($request->query('employee_type'), $this->employeeTypes()),
            'status' => $this->allowedNullableString($request->query('status'), [Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE]),
            'department_id' => $this->nullableInteger($request->query('department_id')),
            'education_level' => $this->allowedNullableString($request->query('education_level'), $this->educationLevels()),
            'hire_date_from' => $this->nullableString($request->query('hire_date_from')),
            'hire_date_to' => $this->nullableString($request->query('hire_date_to')),
            'per_page' => in_array((int) $request->query('per_page', 15), [10, 15, 25, 50], true) ? (int) $request->query('per_page', 15) : 15,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, int $clinicId, ?int $employeeId = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'national_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('employees', 'national_id')->where('clinic_id', $clinicId)->ignore($employeeId),
            ],
            'hire_date' => ['required', 'date'],
            'status' => ['required', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
            'job_title' => ['required', 'string', 'max:255'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('clinic_id', $clinicId),
            ],
            'employee_type' => ['required', Rule::in($this->employeeTypes())],
            'education_level' => ['nullable', Rule::in($this->educationLevels())],
            'certificate_type' => ['nullable', 'string', 'max:255'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'salary_notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
            'gender' => $employee->gender,
            'birth_date' => $employee->birth_date?->toDateString(),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'national_id' => $employee->national_id,
            'hire_date' => $employee->hire_date?->toDateString(),
            'status' => $employee->status,
            'job_title' => $employee->job_title,
            'department_id' => $employee->department_id,
            'department' => $employee->department !== null ? [
                'id' => $employee->department->id,
                'name' => $employee->department->name,
            ] : null,
            'employee_type' => $employee->employee_type,
            'education_level' => $employee->education_level,
            'certificate_type' => $employee->certificate_type,
            'base_salary' => (float) $employee->base_salary,
            'salary_notes' => $employee->salary_notes,
            'salary_payments_count' => (int) ($employee->salary_payments_count ?? 0),
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

    /**
     * @return array<int, string>
     */
    private function employeeTypes(): array
    {
        return [
            Employee::TYPE_RECEPTION,
            Employee::TYPE_NURSE,
            Employee::TYPE_LAB,
            Employee::TYPE_CLEANER,
            Employee::TYPE_GUARD,
            Employee::TYPE_ACCOUNTANT,
            Employee::TYPE_ADMINISTRATIVE,
            Employee::TYPE_OTHER,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function educationLevels(): array
    {
        return [
            Employee::EDUCATION_INSTITUTE,
            Employee::EDUCATION_COLLEGE,
            Employee::EDUCATION_POSTGRADUATE,
            Employee::EDUCATION_NONE,
            Employee::EDUCATION_OTHER,
        ];
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
