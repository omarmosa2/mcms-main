<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:dispatch-reminders {--lead-minutes=60 : Minutes before appointment to queue reminders}')]
#[Description('Queue SMS and WhatsApp reminders for upcoming appointments')]
class DispatchAppointmentRemindersCommand extends Command
{
    public function handle(): int
    {
        $leadMinutes = max(5, (int) $this->option('lead-minutes'));
        $windowStart = now();
        $windowEnd = now()->addMinutes($leadMinutes);
        $channels = [AppointmentReminder::CHANNEL_SMS, AppointmentReminder::CHANNEL_WHATSAPP];
        $queuedCount = 0;

        Appointment::query()
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->whereBetween('scheduled_for', [$windowStart, $windowEnd])
            ->with(['patient:id,clinic_id,phone'])
            ->chunkById(100, function ($appointments) use ($channels, &$queuedCount): void {
                /** @var Appointment $appointment */
                foreach ($appointments as $appointment) {
                    foreach ($channels as $channel) {
                        $reminder = AppointmentReminder::query()->firstOrCreate(
                            [
                                'appointment_id' => $appointment->id,
                                'channel' => $channel,
                                'scheduled_for' => $appointment->scheduled_for,
                            ],
                            [
                                'clinic_id' => $appointment->clinic_id,
                                'status' => AppointmentReminder::STATUS_QUEUED,
                            ],
                        );

                        if (! $reminder->wasRecentlyCreated) {
                            continue;
                        }

                        SendAppointmentReminderJob::dispatch($reminder->id);
                        $queuedCount++;
                    }
                }
            });

        $this->info(sprintf('Queued %d appointment reminder(s).', $queuedCount));

        return self::SUCCESS;
    }
}
