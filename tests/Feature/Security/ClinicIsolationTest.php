<?php

namespace Tests\Feature\Security;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_see_invoices_from_other_clinic(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic1);

        $invoice1 = Invoice::factory()->create(['clinic_id' => $clinic1->id]);
        Invoice::factory()->create(['clinic_id' => $clinic2->id]);

        $this->actingAs($user);

        $visibleInvoices = Invoice::query()->get();

        $this->assertCount(1, $visibleInvoices);
        $this->assertEquals($invoice1->id, $visibleInvoices->first()->id);
    }

    public function test_user_cannot_see_patients_from_other_clinic(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic1);

        $patient1 = Patient::factory()->create(['clinic_id' => $clinic1->id]);
        Patient::factory()->create(['clinic_id' => $clinic2->id]);

        $this->actingAs($user);

        $visiblePatients = Patient::query()->get();

        $this->assertCount(1, $visiblePatients);
        $this->assertEquals($patient1->id, $visiblePatients->first()->id);
    }

    public function test_for_clinic_scope_bypasses_global_scope(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic1);

        Patient::factory()->create(['clinic_id' => $clinic1->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $clinic2->id]);

        $this->actingAs($user);

        $clinic2Patients = Patient::query()->forClinic($clinic2->id)->get();

        $this->assertCount(1, $clinic2Patients);
        $this->assertEquals($patient2->id, $clinic2Patients->first()->id);
    }

    public function test_without_clinic_scope_returns_all_records(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic1);

        Patient::factory()->create(['clinic_id' => $clinic1->id]);
        Patient::factory()->create(['clinic_id' => $clinic2->id]);

        $this->actingAs($user);

        $allPatients = Patient::query()->withoutClinicScope()->get();

        $this->assertCount(2, $allPatients);
    }

    public function test_unauthenticated_user_sees_no_scoped_records(): void
    {
        $clinic = Clinic::factory()->create();
        Patient::factory()->create(['clinic_id' => $clinic->id]);

        $patients = Patient::query()->get();

        $this->assertCount(1, $patients);
    }

    public function test_clinic_id_auto_assigned_on_create(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);

        $this->actingAs($user);

        $patient = Patient::query()->create([
            'file_number' => 1,
            'first_name' => 'Test',
            'last_name' => 'Patient',
        ]);

        $this->assertEquals($clinic->id, $patient->clinic_id);
    }

    private function createAuthenticatedUser(Clinic $clinic): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($user, 'clinic_admin');

        return $user;
    }
}
