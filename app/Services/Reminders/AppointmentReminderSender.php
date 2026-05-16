<?php

namespace App\Services\Reminders;

use App\Models\AppointmentReminder;
use Illuminate\Support\Str;
use RuntimeException;

class AppointmentReminderSender
{
    /**
     * @return array{provider_message_id: string, metadata: array<string, mixed>}
     */
    public function send(AppointmentReminder $reminder): array
    {
        $patientPhone = trim((string) ($reminder->appointment?->patient?->phone ?? ''));

        if ($patientPhone === '') {
            throw new RuntimeException('Patient phone number is missing.');
        }

        $providerMessageId = strtoupper(Str::random(16));

        return [
            'provider_message_id' => $providerMessageId,
            'metadata' => [
                'channel' => $reminder->channel,
                'recipient' => $patientPhone,
                'sent_via' => 'log_driver',
            ],
        ];
    }
}
