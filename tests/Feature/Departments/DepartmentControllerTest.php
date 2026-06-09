<?php

namespace Tests\Feature\Departments;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_clinic_departments(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $department = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'General Medicine',
        ]);

        Department::factory()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Other Clinic Department',
        ]);

        $response = $this->getJson(route('departments.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $department->id);
    }

    public function test_store_creates_department_with_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $payload = [
            'name' => 'Cardiology',
            'code' => 'card',
            'description' => 'Heart and vascular cases',
            'is_active' => true,
        ];

        $response = $this->postJson(route('departments.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Cardiology');
        $response->assertJsonPath('data.code', 'CARD');
        $response->assertJsonPath('data.is_active', true);

        $departmentId = (int) $response->json('data.id');

        $this->assertDatabaseHas('departments', [
            'id' => $departmentId,
            'clinic_id' => $clinic->id,
            'name' => 'Cardiology',
            'code' => 'CARD',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'departments.create',
            'auditable_id' => $departmentId,
        ]);
    }

    public function test_store_saves_clinic_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('departments.store'), [
            'name' => 'Dental Clinic',
            'code' => 'dent',
            'is_active' => true,
            'working_hours' => $this->workingHoursPayload([
                'saturday' => ['start_time' => '09:00', 'end_time' => '17:00'],
                'sunday' => ['start_time' => '10:00', 'end_time' => '16:00'],
            ]),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.working_hours.0.day_of_week', 'saturday');

        $departmentId = (int) $response->json('data.id');

        $this->assertDatabaseHas('clinic_working_hours', [
            'department_id' => $departmentId,
            'day_of_week' => 'saturday',
            'is_active' => true,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertDatabaseHas('clinic_working_hours', [
            'department_id' => $departmentId,
            'day_of_week' => 'friday',
            'is_active' => false,
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    public function test_update_replaces_clinic_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $department = Department::factory()->create(['clinic_id' => $clinic->id]);

        ClinicWorkingHour::query()->create([
            'department_id' => $department->id,
            'day_of_week' => 'saturday',
            'is_active' => true,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response = $this->putJson(route('departments.update', ['departmentId' => $department->id]), [
            'name' => $department->name,
            'working_hours' => $this->workingHoursPayload([
                'monday' => ['start_time' => '08:30', 'end_time' => '14:30'],
            ]),
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('clinic_working_hours', [
            'department_id' => $department->id,
            'day_of_week' => 'saturday',
            'is_active' => false,
            'start_time' => null,
            'end_time' => null,
        ]);

        $this->assertDatabaseHas('clinic_working_hours', [
            'department_id' => $department->id,
            'day_of_week' => 'monday',
            'is_active' => true,
            'start_time' => '08:30',
            'end_time' => '14:30',
        ]);
    }

    public function test_store_rejects_invalid_working_hours(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('departments.store'), [
            'name' => 'Invalid Clinic',
            'working_hours' => [
                [
                    'day_of_week' => 'saturday',
                    'is_active' => true,
                    'start_time' => '17:00',
                    'end_time' => '09:00',
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['working_hours.0.end_time']);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $matchingDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Neuro Search Unit',
        ]);

        Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Orthopedics Unit',
        ]);

        $response = $this->getJson(route('departments.index', ['search' => 'Neuro Search']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingDepartment->id);
    }

    public function test_index_applies_active_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $activeDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'is_active' => true,
        ]);

        Department::factory()->create([
            'clinic_id' => $clinic->id,
            'is_active' => false,
        ]);

        $response = $this->getJson(route('departments.index', ['is_active' => '1']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $activeDepartment->id);
    }

    public function test_index_applies_sorting_by_name(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $firstDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Alpha Department',
        ]);

        $secondDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Zulu Department',
        ]);

        $ascResponse = $this->getJson(route('departments.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $firstDepartment->id);
        $ascResponse->assertJsonPath('data.1.id', $secondDepartment->id);

        $descResponse = $this->getJson(route('departments.index', [
            'sort_by' => 'name',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $secondDepartment->id);
        $descResponse->assertJsonPath('data.1.id', $firstDepartment->id);
    }

    public function test_show_returns_404_for_department_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $department = Department::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        $response = $this->getJson(route('departments.show', ['departmentId' => $department->id]));

        $response->assertNotFound();
    }

    public function test_update_updates_department_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $department = Department::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dermatology',
            'code' => 'DERM',
            'is_active' => true,
        ]);

        $response = $this->putJson(route('departments.update', ['departmentId' => $department->id]), [
            'name' => 'Dermatology and Laser',
            'code' => 'dl',
            'is_active' => false,
            'description' => 'Advanced skin care.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Dermatology and Laser');
        $response->assertJsonPath('data.code', 'DL');
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'clinic_id' => $clinic->id,
            'name' => 'Dermatology and Laser',
            'code' => 'DL',
            'is_active' => false,
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'departments.update',
            'auditable_id' => $department->id,
        ]);
    }

    public function test_destroy_rejects_department_with_assigned_doctor_profiles(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $department = Department::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $doctor = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'department_id' => $department->id,
        ]);

        $response = $this->deleteJson(route('departments.destroy', ['departmentId' => $department->id]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['department']);
        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_bulk_destroy_deletes_only_departments_without_doctor_profiles(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $deletableDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $linkedDepartment = Department::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $doctor = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'department_id' => $linkedDepartment->id,
            'user_id' => $doctor->id,
        ]);

        $response = $this->deleteJson(route('departments.bulk-destroy'), [
            'ids' => [$deletableDepartment->id, $linkedDepartment->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($deletableDepartment);
        $this->assertDatabaseHas('departments', ['id' => $linkedDepartment->id]);
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'clinic_admin'): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, array{start_time: string, end_time: string}>  $activeDays
     * @return array<int, array{day_of_week: string, is_active: bool, start_time: ?string, end_time: ?string}>
     */
    private function workingHoursPayload(array $activeDays): array
    {
        return collect(ClinicWorkingHour::DAYS)
            ->map(fn (string $day): array => [
                'day_of_week' => $day,
                'is_active' => array_key_exists($day, $activeDays),
                'start_time' => $activeDays[$day]['start_time'] ?? null,
                'end_time' => $activeDays[$day]['end_time'] ?? null,
            ])
            ->all();
    }
}
