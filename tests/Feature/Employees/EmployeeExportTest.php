<?php

namespace Tests\Feature\Employees;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Exports\EmployeeExport;
use App\Models\Clinic;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_export_downloads_filtered_employees(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $clinic = Clinic::factory()->create(['name' => 'Employee Export Clinic']);

        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Export Employee',
            'phone' => null,
            'national_id' => 'EXP-001',
            'employee_type' => Employee::TYPE_ACCOUNTANT,
            'status' => Employee::STATUS_ACTIVE,
            'education_level' => Employee::EDUCATION_COLLEGE,
            'base_salary' => 1500,
        ]);

        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Hidden Employee',
            'status' => Employee::STATUS_INACTIVE,
        ]);

        $this->authenticateForClinic($clinic);

        $this->get(route('employees.export', [
            'clinic_id' => $clinic->id,
            'search' => 'EXP-001',
            'status' => Employee::STATUS_ACTIVE,
        ]))->assertOk();

        Excel::assertDownloaded(
            '/employees_export_\d{4}-\d{2}-\d{2}_\d{6}\.xlsx/',
            function (EmployeeExport $export) use ($employee): bool {
                $exportedEmployees = $export->query()->get();
                $row = $export->map($exportedEmployees->first());

                return $exportedEmployees->pluck('id')->all() === [$employee->id]
                    && $row[0] === 'Export Employee'
                    && $row[1] === 'Employee Export Clinic'
                    && $row[3] === ''
                    && $row[4] === 'EXP-001';
            },
        );
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'clinic_admin'): User
    {
        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
