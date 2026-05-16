<?php

namespace App\Jobs;

use App\Actions\Audit\LogAuditAction;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Services\Reminders\AppointmentReminderSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reminderId) {}

    public function handle(AppointmentReminderSender $sender, LogAuditAction $logAuditAction): void
    {
        $reminder = AppointmentReminder::query()
            ->with(['appointment.patient'])
            ->find($this->reminderId);

        if ($reminder === null || $reminder->status !== AppointmentReminder::STATUS_QUEUED) {
            return;
        }

        $appointment = $reminder->appointment;

        if ($appointment === null || in_array($appointment->status, Appointment::TERMINAL_STATUSES, true)) {
            $this->markSkipped($reminder, 'appointment_not_eligible');

            return;
        }

        try {
            $delivery = $sender->send($reminder);

            $reminder->update([
                'status' => AppointmentReminder::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'provider_message_id' => $delivery['provider_message_id'],
                'metadata' => $delivery['metadata'],
            ]);

            $logAuditAction->handle(
                clinicId: (int) $reminder->clinic_id,
                userId: null,
                action: 'appointments.reminder.sent',
                auditable: $appointment,
                metadata: [
                    'reminder_id' => $reminder->id,
                    'channel' => $reminder->channel,
                    'scheduled_for' => $reminder->scheduled_for?->toISOString(),
                    'provider_message_id' => $reminder->provider_message_id,
                ],
            );
        } catch (Throwable $throwable) {
            $reminder->update([
                'status' => AppointmentReminder::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => $throwable->getMessage(),
            ]);
        }
    }

    private function markSkipped(AppointmentReminder $reminder, string $reason): void
    {
        $reminder->update([
            'status' => AppointmentReminder::STATUS_SKIPPED,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }
}
