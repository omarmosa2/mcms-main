<?php

namespace Tests\Feature\Visits;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_starts_visit_and_moves_queue_entry_to_in_service(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_ARRIVED,
        ]);

        $queueEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => QueueEntry::STATUS_CALLED,
        ]);

        $response = $this->postJson(route('visits.store'), [
            'queue_entry_id' => $queueEntry->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'chief_complaint' => 'Headache',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', Visit::STATUS_STARTED);

        $visitId = (int) $response->json('data.id');

        $this->assertDatabaseHas('visits', [
            'id' => $visitId,
            'clinic_id' => $clinic->id,
            'queue_entry_id' => $queueEntry->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $this->assertDatabaseHas('queue_entries', [
            'id' => $queueEntry->id,
            'status' => QueueEntry::STATUS_IN_SERVICE,
        ]);

        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
        $this->assertNotNull($appointment->completed_at);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'visits.start',
            'auditable_id' => $visitId,
        ]);
    }

    public function test_store_rejects_starting_duplicate_visit_for_same_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_ARRIVED,
        ]);

        Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $response = $this->postJson(route('visits.store'), [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['appointment_id']);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $matchingVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VS-SRCH-100',
        ]);

        Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VS-OTHER-200',
        ]);

        $response = $this->getJson(route('visits.index', ['search' => 'SRCH']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingVisit->id);
    }

    public function test_index_applies_sorting_by_visit_number(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $firstVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VS-100',
        ]);

        $secondVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VS-200',
        ]);

        $ascResponse = $this->getJson(route('visits.index', [
            'sort_by' => 'visit_number',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $firstVisit->id);
        $ascResponse->assertJsonPath('data.1.id', $secondVisit->id);

        $descResponse = $this->getJson(route('visits.index', [
            'sort_by' => 'visit_number',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $secondVisit->id);
        $descResponse->assertJsonPath('data.1.id', $firstVisit->id);
    }

    public function test_doctor_index_returns_only_assigned_visits(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherDoctor = User::factory()->create(['clinic_id' => $clinic->id]);

        $assignedVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('visits.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $assignedVisit->id);
    }

    public function test_status_transition_rejects_skipping_required_state(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $response = $this->patchJson(
            route('visits.transition-status', ['visitId' => $visit->id]),
            ['status' => Visit::STATUS_COMPLETED],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_status_transition_to_completed_updates_queue_and_appointment(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_ARRIVED,
            'completed_at' => null,
        ]);

        $queueEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => QueueEntry::STATUS_IN_SERVICE,
            'completed_at' => null,
        ]);

        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'queue_entry_id' => $queueEntry->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_STARTED,
            'in_progress_at' => null,
            'completed_at' => null,
        ]);

        $this->patchJson(
            route('visits.transition-status', ['visitId' => $visit->id]),
            ['status' => Visit::STATUS_IN_PROGRESS],
        )->assertOk();

        $response = $this->patchJson(
            route('visits.transition-status', ['visitId' => $visit->id]),
            ['status' => Visit::STATUS_COMPLETED],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', Visit::STATUS_COMPLETED);

        $visit->refresh();
        $appointment->refresh();
        $queueEntry->refresh();

        $this->assertNotNull($visit->in_progress_at);
        $this->assertNotNull($visit->completed_at);
        $this->assertSame(Visit::STATUS_COMPLETED, $visit->status);

        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
        $this->assertNotNull($appointment->completed_at);

        $this->assertSame(QueueEntry::STATUS_COMPLETED, $queueEntry->status);
        $this->assertNotNull($queueEntry->completed_at);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'visits.transition_status',
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_update_modifies_visit_notes(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $response = $this->putJson(route('visits.update', ['visitId' => $visit->id]), [
            'clinical_notes' => 'Patient is stable.',
            'diagnosis_notes' => 'Likely migraine.',
            'treatment_plan' => 'Hydration and rest.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.clinical_notes', 'Patient is stable.');

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'clinical_notes' => 'Patient is stable.',
            'diagnosis_notes' => 'Likely migraine.',
            'treatment_plan' => 'Hydration and rest.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'visits.update',
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_doctor_cannot_update_visit_assigned_to_another_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'doctor');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherDoctor = User::factory()->create(['clinic_id' => $clinic->id]);

        $visit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $response = $this->putJson(route('visits.update', ['visitId' => $visit->id]), [
            'clinical_notes' => 'Attempted update by another doctor.',
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_deletes_only_started_visits(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $startedVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $this->deleteJson(route('visits.destroy', ['visitId' => $startedVisit->id]))
            ->assertNoContent();

        $this->assertSoftDeleted($startedVisit);

        $completedVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_COMPLETED,
        ]);

        $response = $this->deleteJson(route('visits.destroy', ['visitId' => $completedVisit->id]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_bulk_destroy_deletes_only_started_visits(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $startedVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_STARTED,
        ]);

        $completedVisit = Visit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Visit::STATUS_COMPLETED,
        ]);

        $response = $this->deleteJson(route('visits.bulk-destroy'), [
            'ids' => [$startedVisit->id, $completedVisit->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($startedVisit);
        $this->assertDatabaseHas('visits', ['id' => $completedVisit->id]);
    }

    public function test_show_returns_404_for_visit_from_another_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);
        $visit = Visit::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
        ]);

        $response = $this->getJson(route('visits.show', ['visitId' => $visit->id]));

        $response->assertNotFound();
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
