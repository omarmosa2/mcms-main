<?php

namespace Tests\Feature;

use App\Actions\Patients\ShowPatientAction;
use App\Actions\Patients\UpdatePatientAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_patient_from_different_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $userA = $this->createUserForClinic($clinicA);
        $patientB = Patient::factory()->create(['clinic_id' => $clinicB->id]);

        $action = app(ShowPatientAction::class);

        $this->actingAs($userA);

        $this->expectException(ModelNotFoundException::class);

        $action->handle($clinicA->id, $patientB->id, $userA->id);
    }

    public function test_user_can_access_patient_from_own_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createUserForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $action = app(ShowPatientAction::class);

        $result = $action->handle($clinic->id, $patient->id, $user->id);

        $this->assertEquals($patient->id, $result->id);
    }

    public function test_update_patient_from_different_clinic_fails(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $userA = $this->createUserForClinic($clinicA);
        $patientB = Patient::factory()->create(['clinic_id' => $clinicB->id]);

        $action = app(UpdatePatientAction::class);

        $this->actingAs($userA);

        $this->expectException(ModelNotFoundException::class);

        $action->handle(
            clinicId: $clinicA->id,
            patientId: $patientB->id,
            userId: $userA->id,
            payload: ['first_name' => 'Hacked']
        );
    }

    public function test_factory_creates_patient_with_specified_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->assertEquals($clinic->id, $patient->clinic_id);
    }

    public function test_has_clinic_trait_provides_clinic_relation(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->assertInstanceOf(Clinic::class, $patient->clinic);
        $this->assertEquals($clinic->id, $patient->clinic->id);
    }

    public function test_scope_for_clinic_filters_correctly(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();

        Patient::factory()->create(['clinic_id' => $clinicA->id]);
        Patient::factory()->create(['clinic_id' => $clinicA->id]);
        Patient::factory()->create(['clinic_id' => $clinicB->id]);

        $clinicAPatients = Patient::query()->forClinic($clinicA->id)->count();
        $clinicBPatients = Patient::query()->forClinic($clinicB->id)->count();

        $this->assertEquals(2, $clinicAPatients);
        $this->assertEquals(1, $clinicBPatients);
    }

    private function createUserForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id]);

        return $user;
    }
}
