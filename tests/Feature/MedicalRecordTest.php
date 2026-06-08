<?php

namespace Tests\Feature;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\FollowUp;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $admin;

    private User $doctor;

    private User $receptionist;

    private Patient $patient;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::factory()->create();

        app(SyncClinicRbacAction::class)->handle($this->clinic->id);

        $this->admin = User::factory()->create([
            'clinic_id' => $this->clinic->id,
            'email_verified_at' => now(),
        ]);
        app(AssignUserRoleAction::class)->handle($this->admin, 'clinic_admin');

        $this->doctor = User::factory()->create([
            'clinic_id' => $this->clinic->id,
            'email_verified_at' => now(),
        ]);
        app(AssignUserRoleAction::class)->handle($this->doctor, 'doctor');

        $this->receptionist = User::factory()->create([
            'clinic_id' => $this->clinic->id,
            'email_verified_at' => now(),
        ]);
        app(AssignUserRoleAction::class)->handle($this->receptionist, 'receptionist');

        $this->patient = Patient::factory()->create([
            'clinic_id' => $this->clinic->id,
        ]);

        $this->department = Department::factory()->create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Internal Medicine',
            'clinic_type' => 'internal_medicine',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_medical_records_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/medical-records');

        $response->assertOk();
    }

    public function test_doctor_can_view_medical_records_index(): void
    {
        $response = $this->actingAs($this->doctor)->getJson('/medical-records');

        $response->assertOk();
    }

    public function test_receptionist_cannot_view_medical_records(): void
    {
        $response = $this->actingAs($this->receptionist)->get('/medical-records');

        $response->assertForbidden();
    }

    public function test_admin_can_create_medical_record(): void
    {
        $response = $this->actingAs($this->admin)->post('/medical-records', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'clinic_type' => 'internal_medicine',
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Headache and fever',
            'primary_diagnosis' => 'Upper respiratory infection',
            'status' => 'active',
        ]);

        $response->assertRedirect('/medical-records');

        $this->assertDatabaseHas('medical_records', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'clinic_type' => 'internal_medicine',
            'chief_complaint' => 'Headache and fever',
            'primary_diagnosis' => 'Upper respiratory infection',
            'status' => 'active',
        ]);
    }

    public function test_doctor_can_create_medical_record(): void
    {
        $response = $this->actingAs($this->doctor)->post('/medical-records', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Chest pain',
            'primary_diagnosis' => 'Angina',
            'status' => 'draft',
        ]);

        $response->assertRedirect('/medical-records');

        $this->assertDatabaseHas('medical_records', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'chief_complaint' => 'Chest pain',
        ]);
    }

    public function test_receptionist_cannot_create_medical_record(): void
    {
        $response = $this->actingAs($this->receptionist)->post('/medical-records', [
            'patient_id' => $this->patient->id,
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_view_medical_record(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0001',
            'clinic_type' => 'internal_medicine',
            'chief_complaint' => 'Test complaint',
            'primary_diagnosis' => 'Test diagnosis',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/medical-records/{$record->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $record->id);
        $response->assertJsonPath('data.record_number', 'MR-TEST-0001');
    }

    public function test_admin_can_update_medical_record(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0002',
            'status' => 'draft',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put("/medical-records/{$record->id}", [
            'primary_diagnosis' => 'Updated diagnosis',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'primary_diagnosis' => 'Updated diagnosis',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_delete_medical_record(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0003',
            'status' => 'draft',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete("/medical-records/{$record->id}");

        $response->assertRedirect('/medical-records');

        $this->assertSoftDeleted('medical_records', ['id' => $record->id]);
    }

    public function test_can_create_medical_record_with_treatment_plans(): void
    {
        $response = $this->actingAs($this->admin)->post('/medical-records', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Knee pain',
            'primary_diagnosis' => 'Osteoarthritis',
            'status' => 'active',
            'treatment_plans' => [
                [
                    'title' => 'Physical therapy',
                    'description' => '3 sessions per week',
                    'start_date' => now()->toDateString(),
                    'status' => 'new',
                ],
                [
                    'title' => 'Pain management',
                    'description' => 'NSAIDs as needed',
                    'status' => 'new',
                ],
            ],
        ]);

        $response->assertRedirect('/medical-records');

        $record = MedicalRecord::query()
            ->forClinic($this->clinic->id)
            ->where('patient_id', $this->patient->id)
            ->latest()
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(2, $record->treatmentPlans()->count());
        $this->assertDatabaseHas('treatment_plans', [
            'medical_record_id' => $record->id,
            'title' => 'Physical therapy',
        ]);
    }

    public function test_can_create_medical_record_with_follow_ups(): void
    {
        $response = $this->actingAs($this->admin)->post('/medical-records', [
            'patient_id' => $this->patient->id,
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Follow up needed',
            'status' => 'active',
            'follow_ups' => [
                [
                    'follow_up_date' => now()->addWeek()->toDateString(),
                    'notes' => 'Check blood pressure',
                    'recommended_action' => 'Adjust medication',
                ],
            ],
        ]);

        $response->assertRedirect('/medical-records');

        $record = MedicalRecord::query()
            ->forClinic($this->clinic->id)
            ->where('patient_id', $this->patient->id)
            ->latest()
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(1, $record->followUps()->count());
        $this->assertDatabaseHas('follow_ups', [
            'medical_record_id' => $record->id,
            'notes' => 'Check blood pressure',
        ]);
    }

    public function test_can_add_treatment_plan_to_existing_record(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0004',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/medical-records/treatment-plans', [
            'medical_record_id' => $record->id,
            'patient_id' => $this->patient->id,
            'title' => 'New treatment plan',
            'description' => 'Treatment description',
            'status' => 'new',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('treatment_plans', [
            'medical_record_id' => $record->id,
            'title' => 'New treatment plan',
        ]);
    }

    public function test_can_add_follow_up_to_existing_record(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0005',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/medical-records/follow-ups', [
            'medical_record_id' => $record->id,
            'patient_id' => $this->patient->id,
            'follow_up_date' => now()->addWeek()->toDateString(),
            'notes' => 'Follow up notes',
            'status' => 'scheduled',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('follow_ups', [
            'medical_record_id' => $record->id,
            'notes' => 'Follow up notes',
        ]);
    }

    public function test_can_update_treatment_plan_status(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0006',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $plan = TreatmentPlan::query()->create([
            'clinic_id' => $this->clinic->id,
            'medical_record_id' => $record->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'title' => 'Test plan',
            'status' => 'new',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/medical-records/treatment-plans/{$plan->id}", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('treatment_plans', [
            'id' => $plan->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_can_update_follow_up_status(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-TEST-0007',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $followUp = FollowUp::query()->create([
            'clinic_id' => $this->clinic->id,
            'medical_record_id' => $record->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'follow_up_date' => now()->addWeek()->toDateString(),
            'status' => 'scheduled',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/medical-records/follow-ups/{$followUp->id}", [
            'status' => 'completed',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'status' => 'completed',
        ]);
    }

    public function test_medical_records_are_scoped_to_clinic(): void
    {
        $otherClinic = Clinic::factory()->create();
        app(SyncClinicRbacAction::class)->handle($otherClinic->id);

        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        MedicalRecord::query()->withoutGlobalScope('clinic')->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'record_number' => 'MR-OTHER-0001',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/medical-records');

        $response->assertOk();

        $this->assertDatabaseMissing('medical_records', [
            'clinic_id' => $this->clinic->id,
            'record_number' => 'MR-OTHER-0001',
        ]);
    }

    public function test_create_medical_record_requires_patient_id(): void
    {
        $response = $this->actingAs($this->admin)->post('/medical-records', [
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Test',
        ]);

        $response->assertSessionHasErrors('patient_id');
    }

    public function test_can_filter_medical_records_by_clinic_type(): void
    {
        MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-CARDIO-0001',
            'clinic_type' => 'cardiology',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-PEDIA-0001',
            'clinic_type' => 'pediatrics',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/medical-records?clinic_type=cardiology');

        $response->assertOk();
    }

    public function test_can_create_record_with_dynamic_form_data(): void
    {
        $response = $this->actingAs($this->admin)->post('/medical-records', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'clinic_type' => 'cardiology',
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Chest pain',
            'primary_diagnosis' => 'Hypertension',
            'status' => 'active',
            'form_data' => [
                'blood_pressure' => '140/90',
                'pulse' => '80 bpm',
                'symptoms' => 'Dizziness, headache',
            ],
        ]);

        $response->assertRedirect('/medical-records');

        $record = MedicalRecord::query()
            ->forClinic($this->clinic->id)
            ->where('clinic_type', 'cardiology')
            ->latest()
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('140/90', $record->form_data['blood_pressure']);
        $this->assertEquals('80 bpm', $record->form_data['pulse']);
    }

    public function test_can_export_medical_record_as_pdf(): void
    {
        $record = MedicalRecord::query()->create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'record_number' => 'MR-EXPORT-0001',
            'clinic_type' => 'cardiology',
            'status' => 'active',
            'visit_date' => now()->toDateString(),
            'chief_complaint' => 'Chest pain',
            'primary_diagnosis' => 'Hypertension',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get("/medical-records/{$record->id}/export");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
