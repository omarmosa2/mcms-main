<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\EnqueuePatientAction;
use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\QueueNumberSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_enqueue_assigns_unique_queue_numbers(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $queueDate = now()->toDateString();

        $concurrentCount = 10;
        $results = [];

        for ($i = 0; $i < $concurrentCount; $i++) {
            $nextValue = QueueNumberSequence::getNextValue($clinic->id, $queueDate);
            $results[] = $nextValue;
        }

        $this->assertCount($concurrentCount, $results, 'All concurrent requests should succeed');
        $this->assertCount($concurrentCount, array_unique($results), 'All queue numbers should be unique');

        sort($results);
        for ($i = 0; $i < $concurrentCount; $i++) {
            $this->assertEquals($i + 1, $results[$i], "Queue number at position $i should be ".($i + 1));
        }
    }

    public function test_sequence_resets_per_date(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $date1 = now()->toDateString();
        $date2 = now()->addDay()->toDateString();

        $entry1 = app(EnqueuePatientAction::class)->handle($clinic->id, $user->id, [
            'patient_id' => $patient->id,
            'queue_date' => $date1,
        ]);

        $entry2 = app(EnqueuePatientAction::class)->handle($clinic->id, $user->id, [
            'patient_id' => $patient->id,
            'queue_date' => $date1,
        ]);

        $entry3 = app(EnqueuePatientAction::class)->handle($clinic->id, $user->id, [
            'patient_id' => $patient->id,
            'queue_date' => $date2,
        ]);

        $this->assertEquals(1, $entry1->queue_number);
        $this->assertEquals(2, $entry2->queue_number);
        $this->assertEquals(1, $entry3->queue_number, 'New date should start from 1');
    }

    public function test_sequence_per_clinic_isolation(): void
    {
        $clinic1 = Clinic::factory()->create();
        $clinic2 = Clinic::factory()->create();
        $user1 = $this->createAuthenticatedUser($clinic1);
        $user2 = $this->createAuthenticatedUser($clinic2);
        $patient1 = Patient::factory()->create(['clinic_id' => $clinic1->id]);
        $patient2 = Patient::factory()->create(['clinic_id' => $clinic2->id]);
        $queueDate = now()->toDateString();

        $entry1 = app(EnqueuePatientAction::class)->handle($clinic1->id, $user1->id, [
            'patient_id' => $patient1->id,
            'queue_date' => $queueDate,
        ]);

        $entry2 = app(EnqueuePatientAction::class)->handle($clinic2->id, $user2->id, [
            'patient_id' => $patient2->id,
            'queue_date' => $queueDate,
        ]);

        $this->assertEquals(1, $entry1->queue_number);
        $this->assertEquals(1, $entry2->queue_number, 'Different clinics should have independent sequences');
    }

    public function test_sequence_continues_from_existing_entries(): void
    {
        $clinic = Clinic::factory()->create();
        $user = $this->createAuthenticatedUser($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $queueDate = now()->toDateString();

        QueueEntry::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'queue_date' => $queueDate,
            'queue_number' => 5,
        ]);

        QueueNumberSequence::create([
            'clinic_id' => $clinic->id,
            'queue_date' => $queueDate,
            'current_value' => 5,
        ]);

        $newEntry = app(EnqueuePatientAction::class)->handle($clinic->id, $user->id, [
            'patient_id' => $patient->id,
            'queue_date' => $queueDate,
        ]);

        $this->assertEquals(6, $newEntry->queue_number, 'Sequence should continue from existing max');
    }

    public function test_transaction_rollback_does_not_consume_sequence(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $queueDate = now()->toDateString();

        try {
            DB::transaction(function () use ($clinic, $patient, $queueDate) {
                $nextValue = QueueNumberSequence::getNextValue($clinic->id, $queueDate);

                QueueEntry::create([
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'queue_date' => $queueDate,
                    'queue_number' => $nextValue,
                    'priority' => 0,
                    'status' => QueueEntry::STATUS_WAITING,
                    'checked_in_at' => now(),
                ]);

                throw new \RuntimeException('Simulated validation error');
            });
        } catch (\RuntimeException $e) {
        }

        $user = $this->createAuthenticatedUser($clinic);
        $entry = app(EnqueuePatientAction::class)->handle($clinic->id, $user->id, [
            'patient_id' => $patient->id,
            'queue_date' => $queueDate,
        ]);

        $this->assertEquals(1, $entry->queue_number, 'Rolled back transaction should not consume sequence number');
    }

    public function test_multiple_concurrent_dates_do_not_interfere(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $date1 = now()->toDateString();
        $date2 = now()->addDay()->toDateString();
        $date3 = now()->addDays(2)->toDateString();

        $results = [];

        for ($i = 0; $i < 5; $i++) {
            DB::transaction(function () use ($clinic, $date1, &$results) {
                $nextValue = QueueNumberSequence::getNextValue($clinic->id, $date1);
                $results[$date1][] = $nextValue;
            });

            DB::transaction(function () use ($clinic, $date2, &$results) {
                $nextValue = QueueNumberSequence::getNextValue($clinic->id, $date2);
                $results[$date2][] = $nextValue;
            });

            DB::transaction(function () use ($clinic, $date3, &$results) {
                $nextValue = QueueNumberSequence::getNextValue($clinic->id, $date3);
                $results[$date3][] = $nextValue;
            });
        }

        foreach ([$date1, $date2, $date3] as $date) {
            sort($results[$date]);
            for ($i = 0; $i < 5; $i++) {
                $this->assertEquals($i + 1, $results[$date][$i], "Date $date position $i should be ".($i + 1));
            }
        }
    }

    private function createAuthenticatedUser(Clinic $clinic): User
    {
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($user, 'clinic_admin');

        return $user;
    }
}
