<?php

namespace Tests\Feature\Patients;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_patients_only_for_authenticated_user_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-1000',
        ]);

        Patient::factory()->create([
            'clinic_id' => $otherClinic->id,
            'file_number' => 'PT-2000',
        ]);

        $response = $this->getJson(route('patients.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $patient->id);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'patients.index',
        ]);
    }

    public function test_store_creates_patient_with_clinic_scope_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $payload = [
            'file_number' => 'PT-3000',
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
            'date_of_birth' => '1995-01-12',
            'gender' => 'male',
            'phone' => '0500000000',
            'email' => 'ali@example.com',
            'national_id' => '1234567890',
            'emergency_contact_name' => 'Mona',
            'emergency_contact_phone' => '0511111111',
            'notes' => 'VIP patient',
            'chronic_conditions' => ['Diabetes', 'Hypertension'],
            'allergies' => ['Penicillin'],
            'current_medications' => ['Metformin'],
        ];

        $response = $this->postJson(route('patients.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.file_number', 'PT-3000');
        $response->assertJsonPath('data.clinic_id', $clinic->id);

        $patient = Patient::query()->where('file_number', 'PT-3000')->firstOrFail();

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
        ]);
        $this->assertDatabaseHas('patient_chronic_conditions', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'condition' => 'Diabetes',
        ]);
        $this->assertDatabaseHas('patient_allergies', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'allergy' => 'Penicillin',
        ]);
        $this->assertDatabaseHas('patient_medications', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medication' => 'Metformin',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'patients.create',
            'auditable_type' => $patient->getMorphClass(),
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_store_rejects_medical_profile_entries_longer_than_supported_index_length(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $tooLongValue = str_repeat('A', 192);

        $response = $this->postJson(route('patients.store'), [
            'file_number' => 'PT-3001',
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
            'chronic_conditions' => [$tooLongValue],
            'allergies' => [$tooLongValue],
            'current_medications' => [$tooLongValue],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'chronic_conditions.0',
            'allergies.0',
            'current_medications.0',
        ]);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $matchingPatient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-SRCH-100',
            'first_name' => 'Searchable',
            'last_name' => 'Patient',
        ]);

        Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-OTHER-200',
            'first_name' => 'Different',
            'last_name' => 'Record',
        ]);

        $response = $this->getJson(route('patients.index', ['search' => 'SRCH']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingPatient->id);
    }

    public function test_index_applies_sorting_by_file_number(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $firstPatient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-100',
        ]);

        $secondPatient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-200',
        ]);

        $ascResponse = $this->getJson(route('patients.index', [
            'sort_by' => 'file_number',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $firstPatient->id);
        $ascResponse->assertJsonPath('data.1.id', $secondPatient->id);

        $descResponse = $this->getJson(route('patients.index', [
            'sort_by' => 'file_number',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $secondPatient->id);
        $descResponse->assertJsonPath('data.1.id', $firstPatient->id);
    }

    public function test_show_returns_404_for_patient_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        $response = $this->getJson(route('patients.show', ['patientId' => $patient->id]));

        $response->assertNotFound();
    }

    public function test_show_writes_sensitive_access_log_with_reason(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->getJson(route('patients.show', [
            'patientId' => $patient->id,
            'access_reason' => 'follow-up-care',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.id', $patient->id);

        $this->assertDatabaseHas('sensitive_access_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'resource_type' => 'patient',
            'resource_id' => $patient->id,
            'reason' => 'follow-up-care',
        ]);
    }

    public function test_update_updates_patient_in_same_clinic_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'file_number' => 'PT-4000',
            'first_name' => 'Saleh',
            'last_name' => 'Ahmed',
        ]);

        $response = $this->putJson(route('patients.update', ['patientId' => $patient->id]), [
            'first_name' => 'Saleh Updated',
            'chronic_conditions' => ['Asthma'],
            'allergies' => ['Dust'],
            'current_medications' => ['Inhaler'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.first_name', 'Saleh Updated');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
            'first_name' => 'Saleh Updated',
        ]);
        $this->assertDatabaseHas('patient_chronic_conditions', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'condition' => 'Asthma',
        ]);
        $this->assertDatabaseHas('patient_allergies', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'allergy' => 'Dust',
        ]);
        $this->assertDatabaseHas('patient_medications', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medication' => 'Inhaler',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'patients.update',
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_destroy_deletes_patient_in_same_clinic_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->deleteJson(route('patients.destroy', ['patientId' => $patient->id]));

        $response->assertNoContent();
        $this->assertSoftDeleted($patient);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'patients.delete',
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_bulk_destroy_deletes_selected_patients(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $firstPatient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $secondPatient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $thirdPatient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $response = $this->deleteJson(route('patients.bulk-destroy'), [
            'ids' => [$firstPatient->id, $secondPatient->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 2);
        $response->assertJsonPath('data.failed_count', 0);

        $this->assertSoftDeleted($firstPatient);
        $this->assertSoftDeleted($secondPatient);
        $this->assertDatabaseHas('patients', ['id' => $thirdPatient->id]);
    }

    public function test_show_returns_medical_profile_and_visit_history(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $this->putJson(route('patients.update', ['patientId' => $patient->id]), [
            'chronic_conditions' => ['Chronic Kidney Disease'],
            'allergies' => ['Seafood'],
            'current_medications' => ['Aspirin'],
        ])->assertOk();

        $response = $this->getJson(route('patients.show', ['patientId' => $patient->id]));

        $response->assertOk();
        $response->assertJsonPath('data.chronic_conditions.0', 'Chronic Kidney Disease');
        $response->assertJsonPath('data.allergies.0', 'Seafood');
        $response->assertJsonPath('data.current_medications.0', 'Aspirin');
    }

    public function test_can_upload_download_and_delete_patient_attachment_within_same_clinic(): void
    {
        Storage::fake('local');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $uploadResponse = $this->postJson(
            route('patients.attachments.store', ['patientId' => $patient->id]),
            [
                'file' => UploadedFile::fake()->create('lab-report.pdf', 320, 'application/pdf'),
            ],
        );

        $uploadResponse->assertCreated();
        $uploadResponse->assertJsonPath('data.original_name', 'lab-report.pdf');

        $attachment = PatientAttachment::query()
            ->forClinic($clinic->id)
            ->where('patient_id', $patient->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);

        $downloadResponse = $this->get(route('patients.attachments.download', [
            'patientId' => $patient->id,
            'attachmentId' => $attachment->id,
        ]));

        $downloadResponse->assertOk();
        $this->assertNotNull($downloadResponse->headers->get('content-disposition'));
        $this->assertStringContainsString(
            'lab-report.pdf',
            (string) $downloadResponse->headers->get('content-disposition'),
        );

        $deleteResponse = $this->deleteJson(route('patients.attachments.destroy', [
            'patientId' => $patient->id,
            'attachmentId' => $attachment->id,
        ]));

        $deleteResponse->assertNoContent();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseHas('patient_attachments', [
            'id' => $attachment->id,
        ]);
        $this->assertSoftDeleted('patient_attachments', [
            'id' => $attachment->id,
        ]);
    }

    public function test_attachment_upload_rejects_unsupported_file_type(): void
    {
        Storage::fake('local');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->postJson(
            route('patients.attachments.store', ['patientId' => $patient->id]),
            [
                'file' => UploadedFile::fake()->create('malware.exe', 32, 'application/octet-stream'),
            ],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_attachment_upload_rejects_file_larger_than_ten_mb(): void
    {
        Storage::fake('local');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->postJson(
            route('patients.attachments.store', ['patientId' => $patient->id]),
            [
                'file' => UploadedFile::fake()->create('oversized.pdf', 11000, 'application/pdf'),
            ],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_attachment_routes_are_scoped_to_user_clinic(): void
    {
        Storage::fake('local');

        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $otherPatient = Patient::factory()->create([
            'clinic_id' => $otherClinic->id,
        ]);

        $attachment = PatientAttachment::query()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'uploaded_by' => null,
            'disk' => 'local',
            'path' => 'patients/other/attachments/foreign.pdf',
            'original_name' => 'foreign.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
        ]);

        Storage::disk('local')->put($attachment->path, 'foreign');

        $downloadResponse = $this->get(route('patients.attachments.download', [
            'patientId' => $otherPatient->id,
            'attachmentId' => $attachment->id,
        ]));
        $downloadResponse->assertNotFound();

        $deleteResponse = $this->deleteJson(route('patients.attachments.destroy', [
            'patientId' => $otherPatient->id,
            'attachmentId' => $attachment->id,
        ]));
        $deleteResponse->assertNotFound();
    }

    private function authenticateForClinic(Clinic $clinic): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, 'clinic_admin');

        $this->actingAs($user);

        return $user;
    }
}
