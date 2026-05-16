<?php

namespace Tests\Feature\Appointments;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_sms_and_whatsapp_reminders_for_upcoming_appointments(): void
    {
        Queue::fake();

        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'phone' => '966500000001',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_for' => now()->addMinutes(30),
        ]);

        $this->artisan('appointments:dispatch-reminders --lead-minutes=60')
            ->assertSuccessful();

        $this->assertDatabaseHas('appointment_reminders', [
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => AppointmentReminder::CHANNEL_SMS,
            'status' => AppointmentReminder::STATUS_QUEUED,
        ]);

        $this->assertDatabaseHas('appointment_reminders', [
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => AppointmentReminder::CHANNEL_WHATSAPP,
            'status' => AppointmentReminder::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendAppointmentReminderJob::class, 2);
    }

    public function test_job_marks_reminder_as_sent_and_writes_audit_log(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'phone' => '966500000002',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_for' => now()->addMinutes(40),
        ]);

        $reminder = AppointmentReminder::query()->create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => AppointmentReminder::CHANNEL_SMS,
            'status' => AppointmentReminder::STATUS_QUEUED,
            'scheduled_for' => $appointment->scheduled_for,
        ]);

        SendAppointmentReminderJob::dispatchSync($reminder->id);

        $this->assertDatabaseHas('appointment_reminders', [
            'id' => $reminder->id,
            'status' => AppointmentReminder::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'action' => 'appointments.reminder.sent',
            'auditable_id' => $appointment->id,
        ]);
    }

    public function test_job_marks_reminder_as_failed_when_phone_is_missing(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'phone' => null,
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_for' => now()->addMinutes(50),
        ]);

        $reminder = AppointmentReminder::query()->create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => AppointmentReminder::CHANNEL_WHATSAPP,
            'status' => AppointmentReminder::STATUS_QUEUED,
            'scheduled_for' => $appointment->scheduled_for,
        ]);

        SendAppointmentReminderJob::dispatchSync($reminder->id);

        $this->assertDatabaseHas('appointment_reminders', [
            'id' => $reminder->id,
            'status' => AppointmentReminder::STATUS_FAILED,
        ]);
    }
}
