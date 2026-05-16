<?php

namespace Tests\Feature\Queue;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_clinic_queue_entries(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $entry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);

        QueueEntry::factory()->create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
        ]);

        $response = $this->getJson(route('queue.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_store_enqueues_patient_with_incremented_queue_number(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);

        QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'queue_date' => '2026-04-13',
            'queue_number' => 1,
        ]);

        $response = $this->postJson(route('queue.store'), [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'queue_date' => '2026-04-13',
            'priority' => 2,
            'notes' => 'urgent',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.queue_number', 2);
        $response->assertJsonPath('data.status', QueueEntry::STATUS_WAITING);

        $entryId = (int) $response->json('data.id');

        $this->assertDatabaseHas('queue_entries', [
            'id' => $entryId,
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'queue_number' => 2,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'queue.enqueue',
            'auditable_id' => $entryId,
        ]);
    }

    public function test_index_applies_search_filter(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'QueueSearch',
            'last_name' => 'Patient',
        ]);

        $matchingEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);

        QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => Patient::factory()->create(['clinic_id' => $clinic->id])->id,
        ]);

        $response = $this->getJson(route('queue.index', ['search' => 'QueueSearch']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_index_applies_sorting_by_queue_number(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $firstEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'queue_number' => 10,
        ]);

        $secondEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'queue_number' => 20,
        ]);

        $ascResponse = $this->getJson(route('queue.index', [
            'sort_by' => 'queue_number',
            'sort_direction' => 'asc',
        ]));

        $ascResponse->assertOk();
        $ascResponse->assertJsonPath('data.0.id', $firstEntry->id);
        $ascResponse->assertJsonPath('data.1.id', $secondEntry->id);

        $descResponse = $this->getJson(route('queue.index', [
            'sort_by' => 'queue_number',
            'sort_direction' => 'desc',
        ]));

        $descResponse->assertOk();
        $descResponse->assertJsonPath('data.0.id', $secondEntry->id);
        $descResponse->assertJsonPath('data.1.id', $firstEntry->id);
    }

    public function test_doctor_index_returns_only_entries_assigned_to_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherDoctor = User::factory()->create(['clinic_id' => $clinic->id]);

        $assignedEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'assigned_doctor_id' => $doctor->id,
        ]);

        QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'assigned_doctor_id' => $otherDoctor->id,
        ]);

        $response = $this->getJson(route('queue.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $assignedEntry->id);
    }

    public function test_call_next_picks_highest_priority_then_lowest_queue_number(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'receptionist');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $normal = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'queue_date' => '2026-04-14',
            'queue_number' => 1,
            'priority' => 0,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        $priority = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'queue_date' => '2026-04-14',
            'queue_number' => 2,
            'priority' => 3,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        $response = $this->postJson(route('queue.call-next'), [
            'queue_date' => '2026-04-14',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $priority->id);
        $response->assertJsonPath('data.status', QueueEntry::STATUS_CALLED);

        $this->assertDatabaseHas('queue_entries', [
            'id' => $priority->id,
            'status' => QueueEntry::STATUS_CALLED,
            'called_by' => $user->id,
        ]);

        $this->assertDatabaseHas('queue_entries', [
            'id' => $normal->id,
            'status' => QueueEntry::STATUS_WAITING,
        ]);
    }

    public function test_update_status_rejects_invalid_transition(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $entry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        $response = $this->patchJson(
            route('queue.update-status', ['queueEntryId' => $entry->id]),
            ['status' => QueueEntry::STATUS_COMPLETED],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_update_status_progresses_entry_to_completed(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $entry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        $this->patchJson(
            route('queue.update-status', ['queueEntryId' => $entry->id]),
            ['status' => QueueEntry::STATUS_CALLED],
        )->assertOk();

        $this->patchJson(
            route('queue.update-status', ['queueEntryId' => $entry->id]),
            ['status' => QueueEntry::STATUS_IN_SERVICE],
        )->assertOk();

        $response = $this->patchJson(
            route('queue.update-status', ['queueEntryId' => $entry->id]),
            ['status' => QueueEntry::STATUS_COMPLETED],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', QueueEntry::STATUS_COMPLETED);

        $entry->refresh();
        $this->assertNotNull($entry->called_at);
        $this->assertNotNull($entry->started_at);
        $this->assertNotNull($entry->completed_at);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'action' => 'queue.update_status',
            'auditable_id' => $entry->id,
        ]);
    }

    public function test_destroy_deletes_only_waiting_or_skipped_entries(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $waitingEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        $this->deleteJson(route('queue.destroy', ['queueEntryId' => $waitingEntry->id]))
            ->assertNoContent();

        $this->assertSoftDeleted($waitingEntry);

        $completedEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => QueueEntry::STATUS_COMPLETED,
        ]);

        $response = $this->deleteJson(route('queue.destroy', ['queueEntryId' => $completedEntry->id]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_bulk_destroy_deletes_only_waiting_or_skipped_entries(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $waitingEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        $completedEntry = QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => QueueEntry::STATUS_COMPLETED,
        ]);

        $response = $this->deleteJson(route('queue.bulk-destroy'), [
            'ids' => [$waitingEntry->id, $completedEntry->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.deleted_count', 1);
        $response->assertJsonPath('data.failed_count', 1);

        $this->assertSoftDeleted($waitingEntry);
        $this->assertDatabaseHas('queue_entries', ['id' => $completedEntry->id]);
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
