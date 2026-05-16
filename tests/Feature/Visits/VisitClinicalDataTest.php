<?php

namespace Tests\Feature\Visits;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitClinicalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_admin_can_store_icd10_diagnosis_for_visit(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->postJson(route('visits.diagnoses.store', ['visitId' => $visit->id]), [
            'icd10_code' => 'J20.9',
            'diagnosis_title' => 'Acute bronchitis, unspecified',
            'is_primary' => true,
            'notes' => 'Initial clinical diagnosis',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.icd10_code', 'J20.9');
        $response->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('visit_diagnoses', [
            'clinic_id' => $clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'icd10_code' => 'J20.9',
            'diagnosis_title' => 'Acute bronchitis, unspecified',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'visits.diagnoses.create',
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_clinic_admin_can_store_vital_signs_for_visit(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->postJson(route('visits.vitals.store', ['visitId' => $visit->id]), [
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 72,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98,
            'temperature_celsius' => 36.8,
            'weight_kg' => 73.2,
            'height_cm' => 176.4,
            'notes' => 'Stable vitals',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.systolic_bp', 120);
        $response->assertJsonPath('data.oxygen_saturation', 98);

        $this->assertDatabaseHas('visit_vital_signs', [
            'clinic_id' => $clinic->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'recorded_by' => $user->id,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 72,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'visits.vitals.create',
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_clinical_data_routes_are_scoped_by_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $patient = Patient::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        $otherClinicVisit = Visit::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $patient->id,
        ]);

        $this->postJson(route('visits.diagnoses.store', ['visitId' => $otherClinicVisit->id]), [
            'icd10_code' => 'R50.9',
        ])->assertNotFound();

        $this->postJson(route('visits.vitals.store', ['visitId' => $otherClinicVisit->id]), [
            'heart_rate' => 77,
        ])->assertNotFound();
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);
        $this->actingAs($user);

        return $user;
    }
}
