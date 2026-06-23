<?php

namespace Tests\Feature\Departments;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->getJson(route('clinics.index'));

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_index_excludes_administrative_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        Clinic::factory()->create([
            'code' => 'ADMIN001',
            'name' => 'Administration Clinic',
            'is_administrative' => true,
        ]);

        $response = $this->getJson(route('clinics.index'));

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonMissing(['name' => 'Administration Clinic']);
    }

    public function test_store_creates_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $payload = [
            'name' => 'Cardiology',
            'code' => 'card',
            'description' => 'Heart and vascular cases',
            'is_active' => true,
        ];

        $response = $this->postJson(route('clinics.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Cardiology');
        $response->assertJsonPath('data.code', 'card');
        $response->assertJsonPath('data.is_active', true);

        $clinicId = (int) $response->json('data.id');

        $this->assertDatabaseHas('clinics', [
            'id' => $clinicId,
            'name' => 'Cardiology',
            'code' => 'card',
            'is_active' => true,
        ]);
    }

    public function test_store_allows_the_clinic_code_to_be_omitted(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->postJson(route('clinics.store'), [
            'name' => 'Dental Center',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', null);
        $this->assertDatabaseHas('clinics', [
            'name' => 'Dental Center',
            'code' => null,
        ]);
    }

    public function test_update_allows_the_clinic_code_to_be_cleared(): void
    {
        $clinic = Clinic::factory()->create(['code' => 'DENT']);
        $this->authenticateForClinic($clinic);

        $response = $this->putJson(route('clinics.update', ['clinicId' => $clinic->id]), [
            'name' => $clinic->name,
            'code' => null,
            'is_active' => $clinic->is_active,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', null);
        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'code' => null,
        ]);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        Clinic::factory()->create([
            'name' => 'Neuro Search Unit',
        ]);

        Clinic::factory()->create([
            'name' => 'Orthopedics Unit',
        ]);

        $response = $this->getJson(route('clinics.index', ['search' => 'Neuro Search']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_applies_active_filter(): void
    {
        $clinic = Clinic::factory()->create(['is_active' => true]);
        $this->authenticateForClinic($clinic);

        Clinic::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('clinics.index', ['is_active' => '1']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_applies_sorting_by_name(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        Clinic::factory()->create(['name' => 'Alpha Clinic']);
        Clinic::factory()->create(['name' => 'Zulu Clinic']);

        $ascResponse = $this->getJson(route('clinics.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();

        $descResponse = $this->getJson(route('clinics.index', [
            'sort_by' => 'name',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
    }

    public function test_update_updates_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $response = $this->putJson(route('clinics.update', ['clinicId' => $clinic->id]), [
            'name' => 'Dermatology and Laser',
            'code' => 'dl',
            'is_active' => false,
            'description' => 'Advanced skin care.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Dermatology and Laser');
        $response->assertJsonPath('data.code', 'dl');
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('clinics', [
            'id' => $clinic->id,
            'name' => 'Dermatology and Laser',
            'code' => 'dl',
            'is_active' => false,
        ]);
    }

    public function test_destroy_rejects_clinic_with_assigned_employees(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        Employee::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->deleteJson(route('clinics.destroy', ['clinicId' => $clinic->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }

    public function test_destroy_rejects_clinic_with_assigned_users(): void
    {
        $adminClinic = Clinic::factory()->create();
        $this->authenticateForClinic($adminClinic);

        $clinic = Clinic::factory()->create();
        User::factory()->create(['clinic_id' => $clinic->id]);

        $response = $this->deleteJson(route('clinics.destroy', ['clinicId' => $clinic->id]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('clinic');
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }

    public function test_destroy_rejects_clinic_with_assigned_doctors(): void
    {
        $adminClinic = Clinic::factory()->create();
        $this->authenticateForClinic($adminClinic);

        $clinic = Clinic::factory()->create();
        DoctorProfile::factory()->create(['clinic_id' => $clinic->id]);

        $response = $this->deleteJson(route('clinics.destroy', ['clinicId' => $clinic->id]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('clinic');
        $response->assertJsonPath('errors.clinic.0', 'لا يمكن حذف العيادة بينما يوجد مستخدمون أو موظفون أو أطباء مرتبطون بها.');
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }

    public function test_bulk_destroy_deletes_only_empty_clinics(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $deletableClinic = Clinic::factory()->create();
        $linkedClinic = Clinic::factory()->create();

        Employee::factory()->create([
            'clinic_id' => $linkedClinic->id,
        ]);

        $this->assertDatabaseCount('employees', 1);

        $response = $this->deleteJson(route('clinics.bulk-destroy'), [
            'ids' => [$deletableClinic->id, $linkedClinic->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertDatabaseMissing('clinics', ['id' => $deletableClinic->id]);
        $this->assertDatabaseHas('clinics', ['id' => $linkedClinic->id]);
    }

    public function test_bulk_destroy_skips_clinics_with_assigned_doctors(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $deletableClinic = Clinic::factory()->create();
        $linkedClinic = Clinic::factory()->create();
        DoctorProfile::factory()->create(['clinic_id' => $linkedClinic->id]);

        $response = $this->deleteJson(route('clinics.bulk-destroy'), [
            'ids' => [$deletableClinic->id, $linkedClinic->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);
        $this->assertDatabaseMissing('clinics', ['id' => $deletableClinic->id]);
        $this->assertDatabaseHas('clinics', ['id' => $linkedClinic->id]);
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
}
