<?php

namespace Tests\Feature\Employees;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
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
        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Searchable Employee',
            'employee_type' => Employee::TYPE_RECEPTION,
        ]);

        $response = $this->getJson(route('employees.index', ['search' => 'Searchable']));

        $response->assertOk();
        $response->assertJsonPath('employees.data.0.full_name', 'Searchable Employee');
        $response->assertJsonPath('options.clinics.0.id', $clinic->id);
    }

    public function test_non_admin_cannot_access_employee_management(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');

        $this->get(route('employees.index'))->assertForbidden();
    }

    public function test_admin_can_store_employee_with_all_fields(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('employees.store'), [
            'clinic_id' => $clinic->id,
            'full_name' => 'New Reception Employee',
            'gender' => 'female',
            'birth_date' => '1995-02-10',
            'address' => 'Damascus',
            'national_id' => 'EMP-100',
            'marital_status' => Employee::MARITAL_SINGLE,
            'hire_date' => '2026-06-01',
            'status' => Employee::STATUS_ACTIVE,
            'employee_type' => Employee::TYPE_RECEPTION,
            'job_description' => 'Manages front desk operations',
            'education_level' => Employee::EDUCATION_COLLEGE,
            'certificate_name' => 'Bachelor of Business Administration',
            'education_specialty' => 'Business Administration',
            'graduation_year' => 2018,
            'issuing_institution' => 'Damascus University',
            'base_salary' => 1200,
            'additional_allowance' => 200,
            'salary_notes' => 'Monthly fixed salary.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('employees', [
            'clinic_id' => $clinic->id,
            'full_name' => 'New Reception Employee',
            'employee_type' => Employee::TYPE_RECEPTION,
            'job_title' => Employee::TYPE_RECEPTION,
            'marital_status' => Employee::MARITAL_SINGLE,
            'phone' => null,
            'certificate_name' => 'Bachelor of Business Administration',
            'graduation_year' => 2018,
            'issuing_institution' => 'Damascus University',
            'base_salary' => 1200,
            'additional_allowance' => 200,
        ]);
    }

    public function test_admin_can_store_employee_with_user_account(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('employees.store'), [
            'clinic_id' => $clinic->id,
            'full_name' => 'Receptionist With Account',
            'gender' => 'male',
            'phone' => '+963999777888',
            'hire_date' => '2026-06-01',
            'status' => Employee::STATUS_ACTIVE,
            'employee_type' => Employee::TYPE_RECEPTION,
            'base_salary' => 800,
            'create_account' => true,
            'email' => 'receptionist@clinic.com',
            'password' => 'securepassword123',
            'role_name' => 'receptionist',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('employees', [
            'clinic_id' => $clinic->id,
            'full_name' => 'Receptionist With Account',
        ]);

        $employee = Employee::query()->where('full_name', 'Receptionist With Account')->first();
        $this->assertNotNull($employee->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $employee->user_id,
            'email' => 'receptionist@clinic.com',
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_store_employee_without_account_does_not_create_user(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('employees.store'), [
            'clinic_id' => $clinic->id,
            'full_name' => 'Cleaner No Account',
            'gender' => 'male',
            'phone' => '+963999111222',
            'hire_date' => '2026-06-01',
            'status' => Employee::STATUS_ACTIVE,
            'employee_type' => Employee::TYPE_CLEANER,
            'base_salary' => 500,
            'create_account' => false,
        ]);

        $response->assertCreated();

        $employee = Employee::query()->where('full_name', 'Cleaner No Account')->first();
        $this->assertNull($employee->user_id);
    }

    public function test_admin_can_update_employee(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'full_name' => 'Old Name',
            'base_salary' => 500,
        ]);

        $response = $this->putJson(route('employees.update', $employee), [
            'clinic_id' => $clinic->id,
            'full_name' => 'Updated Name',
            'gender' => 'male',
            'phone' => null,
            'hire_date' => '2026-01-01',
            'status' => Employee::STATUS_ACTIVE,
            'employee_type' => Employee::TYPE_ACCOUNTANT,
            'base_salary' => 1500,
            'additional_allowance' => 300,
            'marital_status' => Employee::MARITAL_MARRIED,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'full_name' => 'Updated Name',
            'base_salary' => 1500,
            'phone' => null,
            'job_title' => Employee::TYPE_ACCOUNTANT,
            'marital_status' => Employee::MARITAL_MARRIED,
        ]);
    }

    public function test_direct_employee_get_redirects_to_index(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create(['clinic_id' => $clinic->id]);

        $response = $this->get(route('employees.show', $employee));

        $response->assertRedirect(route('employees.index'));
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

    public function test_destroy_deletes_employee_without_salary_payments(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $employee = Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => Employee::STATUS_ACTIVE,
        ]);

        $this->deleteJson(route('employees.destroy', $employee))->assertNoContent();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_search_includes_national_id(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        Employee::factory()->create([
            'clinic_id' => $clinic->id,
            'national_id' => 'UniqueNationalXYZ',
        ]);

        $response = $this->getJson(route('employees.index', ['search' => 'UniqueNationalXYZ']));

        $response->assertOk();
        $this->assertNotEmpty($response->json('employees.data'));
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
