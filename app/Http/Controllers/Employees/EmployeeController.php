<?php

namespace App\Http\Controllers\Employees;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Exports\EmployeeExport;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = $this->resolveFilters($request);

        $employees = $this->employeesQuery($filters)
            ->with(['clinic:id,name,code', 'user:id,name,email'])
            ->withCount('salaryPayments')
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $payload = [
            'employees' => $employees->through(fn (Employee $employee): array => $this->employeePayload($employee))->toArray(),
            'filters' => $filters,
            'stats' => [
                'total' => $this->employeesQuery($filters, false)->count(),
                'active' => $this->employeesQuery($filters, false)->where('status', Employee::STATUS_ACTIVE)->count(),
                'inactive' => $this->employeesQuery($filters, false)->where('status', Employee::STATUS_INACTIVE)->count(),
                'monthly_salaries' => (float) $this->employeesQuery($filters, false)->where('status', Employee::STATUS_ACTIVE)->sum('base_salary'),
            ],
            'options' => [
                'clinics' => $this->clinicOptions(),
                'employee_types' => $this->employeeTypes(),
                'education_levels' => $this->educationLevels(),
                'statuses' => [Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE],
                'marital_statuses' => $this->maritalStatuses(),
                'account_roles' => $this->accountRoles(),
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('employees/Index', $payload);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $clinicId = (int) $payload['clinic_id'];
        $createAccount = $request->boolean('create_account');
        $accountData = $createAccount ? $this->validatedAccountPayload($request, $clinicId) : null;

        $employee = DB::transaction(function () use ($payload, $accountData, $clinicId, $request): Employee {
            $userId = null;

            if ($accountData !== null) {
                $user = User::query()->create([
                    'clinic_id' => $clinicId,
                    'name' => $payload['full_name'],
                    'email' => $accountData['email'],
                    'password' => Hash::make($accountData['password']),
                    'is_active' => true,
                ]);

                app(AssignUserRoleAction::class)->handle($user, $accountData['role_name'], (int) $request->user()->id);

                $userId = $user->id;
            }

            return Employee::query()->create([
                ...$payload,
                'user_id' => $userId,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->employeePayload($employee->load(['user']))], Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee saved successfully.']);

        return to_route('employees.index');
    }

    public function update(Request $request, int $employeeId): JsonResponse|RedirectResponse
    {
        $employee = Employee::query()->withoutClinicScope()->findOrFail($employeeId);
        $payload = $this->validatedPayload($request, $employee->id);

        $employee->update($payload);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->employeePayload($employee->refresh()->load(['user']))]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee updated successfully.']);

        return to_route('employees.index');
    }

    public function show(int $employeeId): RedirectResponse
    {
        Employee::query()->withoutClinicScope()->findOrFail($employeeId);

        return to_route('employees.index');
    }

    public function destroy(Request $request, int $employeeId): JsonResponse|RedirectResponse
    {
        $employee = Employee::query()->withoutClinicScope()->withCount('salaryPayments')->findOrFail($employeeId);

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

    public function export(Request $request): BinaryFileResponse|StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $filename = 'employees_export_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(
            new EmployeeExport($filters),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'search' => $this->nullableString($request->query('search')),
            'clinic_id' => $this->nullableInteger($request->query('clinic_id')),
            'employee_type' => $this->allowedNullableString($request->query('employee_type'), $this->employeeTypes()),
            'status' => $this->allowedNullableString($request->query('status'), [Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE]),
            'education_level' => $this->allowedNullableString($request->query('education_level'), $this->educationLevels()),
            'hire_date_from' => $this->nullableString($request->query('hire_date_from')),
            'hire_date_to' => $this->nullableString($request->query('hire_date_to')),
            'per_page' => in_array((int) $request->query('per_page', 15), [10, 15, 25, 50], true) ? (int) $request->query('per_page', 15) : 15,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?int $employeeId = null): array
    {
        $validated = $request->validate([
            'clinic_id' => [
                'required',
                'integer',
                Rule::exists('clinics', 'id')->where('is_active', true),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'national_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('employees', 'national_id')->where('clinic_id', (int) $request->input('clinic_id'))->ignore($employeeId),
            ],
            'marital_status' => ['nullable', Rule::in($this->maritalStatuses())],
            'hire_date' => ['required', 'date'],
            'status' => ['required', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
            'employee_type' => ['required', Rule::in($this->employeeTypes())],
            'job_description' => ['nullable', 'string', 'max:2000'],
            'education_level' => ['nullable', Rule::in($this->educationLevels())],
            'certificate_name' => ['nullable', 'string', 'max:255'],
            'education_specialty' => ['nullable', 'string', 'max:150'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'issuing_institution' => ['nullable', 'string', 'max:255'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'additional_allowance' => ['nullable', 'numeric', 'min:0'],
            'salary_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            ...$validated,
            'job_title' => $validated['employee_type'],
            'specialty' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAccountPayload(Request $request, int $clinicId): array
    {
        return $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('clinic_id', $clinicId),
            ],
            'password' => ['required', 'string', 'min:8'],
            'role_name' => ['required', Rule::in($this->accountRoles())],
        ]);
    }

    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'clinic_id' => $employee->clinic_id,
            'clinic' => $employee->clinic !== null ? [
                'id' => $employee->clinic->id,
                'name' => $employee->clinic->name,
                'code' => $employee->clinic->code,
            ] : null,
            'full_name' => $employee->full_name,
            'gender' => $employee->gender,
            'birth_date' => $employee->birth_date?->toDateString(),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'national_id' => $employee->national_id,
            'marital_status' => $employee->marital_status,
            'hire_date' => $employee->hire_date?->toDateString(),
            'status' => $employee->status,
            'employee_type' => $employee->employee_type,
            'job_description' => $employee->job_description,
            'education_level' => $employee->education_level,
            'certificate_name' => $employee->certificate_name,
            'education_specialty' => $employee->education_specialty,
            'graduation_year' => $employee->graduation_year,
            'issuing_institution' => $employee->issuing_institution,
            'base_salary' => (float) $employee->base_salary,
            'additional_allowance' => $employee->additional_allowance !== null ? (float) $employee->additional_allowance : null,
            'salary_notes' => $employee->salary_notes,
            'salary_payments_count' => (int) ($employee->salary_payments_count ?? 0),
            'user' => $employee->user !== null ? [
                'id' => $employee->user->id,
                'name' => $employee->user->name,
                'email' => $employee->user->email,
            ] : null,
        ];
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

    /**
     * @return array<int, string>
     */
    private function educationLevels(): array
    {
        return [
            Employee::EDUCATION_NONE,
            Employee::EDUCATION_SECONDARY,
            Employee::EDUCATION_INSTITUTE,
            Employee::EDUCATION_COLLEGE,
            Employee::EDUCATION_POSTGRADUATE,
            Employee::EDUCATION_OTHER,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function maritalStatuses(): array
    {
        return [
            Employee::MARITAL_SINGLE,
            Employee::MARITAL_MARRIED,
            Employee::MARITAL_DIVORCED,
            Employee::MARITAL_WIDOWED,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function accountRoles(): array
    {
        return ['receptionist', 'admin', 'accountant'];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function employeesQuery(array $filters, bool $includeSearch = true): Builder
    {
        return Employee::query()
            ->withoutClinicScope()
            ->when($filters['clinic_id'] !== null, fn (Builder $query) => $query->where('clinic_id', $filters['clinic_id']))
            ->when($includeSearch && $filters['search'] !== null, function (Builder $query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%");
                });
            })
            ->when($filters['employee_type'] !== null, fn (Builder $query) => $query->where('employee_type', $filters['employee_type']))
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['education_level'] !== null, fn (Builder $query) => $query->where('education_level', $filters['education_level']))
            ->when($filters['hire_date_from'] !== null, fn (Builder $query) => $query->whereDate('hire_date', '>=', $filters['hire_date_from']))
            ->when($filters['hire_date_to'] !== null, fn (Builder $query) => $query->whereDate('hire_date', '<=', $filters['hire_date_to']));
    }

    /**
     * @return array<int, array{id: int, name: string, code: ?string}>
     */
    private function clinicOptions(): array
    {
        return Clinic::query()
            ->clinical()
            ->where('is_active', true)
            ->select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get()
            ->map(fn (Clinic $clinic): array => [
                'id' => (int) $clinic->id,
                'name' => $clinic->name,
                'code' => $clinic->code,
            ])
            ->all();
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
