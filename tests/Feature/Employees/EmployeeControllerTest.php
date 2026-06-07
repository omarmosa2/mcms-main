<?php

namespace Tests\Feature\Employees;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_employee_management_page(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Reception']);
        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'department_id' => $department->id,
            'full_name' => 'Searchable Employee',
            'employee_type' => Employee::TYPE_RECEPTION,
        ]);

        $response = $this->getJson(route('employees.index', ['search' => 'Searchable']));

        $response->assertOk();
        $response->assertJsonPath('employees.data.0.full_name', 'Searchable Employee');
        $response->assertJsonPath('departments.0.name', 'Reception');
    }

    public function test_non_admin_cannot_access_employee_management(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $this->get(route('employees.index'))->assertForbidden();
    }

    public function test_admin_can_store_employee(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);

        $response = $this->postJson(route('employees.store'), [
            'full_name' => 'New Reception Employee',
            'gender' => 'female',
            'birth_date' => '1995-02-10',
            'phone' => '+963999123456',
            'address' => 'Damascus',
            'national_id' => 'EMP-100',
            'hire_date' => '2026-06-01',
            'status' => Employee::STATUS_ACTIVE,
            'job_title' => 'Reception Supervisor',
            'department_id' => $department->id,
            'employee_type' => Employee::TYPE_RECEPTION,
            'education_level' => Employee::EDUCATION_COLLEGE,
            'certificate_type' => 'Business Administration',
            'base_salary' => 1200,
            'salary_notes' => 'Monthly fixed salary.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employees', [
            'clinic_id' => $clinic->id,
            'full_name' => 'New Reception Employee',
            'employee_type' => Employee::TYPE_RECEPTION,
            'base_salary' => 1200,
        ]);
    }

    public function test_destroy_archives_employee_with_salary_payments(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);
        EmployeeSalaryPayment::factory()->create([
            'clinic_id' => $clinic->id,
            'employee_id' => $employee->id,
        ]);

        $this->deleteJson(route('employees.destroy', $employee))->assertNoContent();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => Employee::STATUS_INACTIVE,
            'deleted_at' => null,
        ]);
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
