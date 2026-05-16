<?php

namespace App\Jobs;

use App\Models\ExternalIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchLabResultToLisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $integrationId) {}

    public function handle(): void
    {
        $integration = ExternalIntegration::query()->find($this->integrationId);

        if ($integration === null || $integration->integration_type !== ExternalIntegration::TYPE_LIS_HL7) {
            return;
        }

        try {
            $requestPayload = is_array($integration->request_payload) ? $integration->request_payload : [];
            $hl7MessageId = sprintf('LIS-%d-%s', $integration->id, now()->format('His'));

            $integration->update([
                'status' => ExternalIntegration::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'response_payload' => [
                    'ack' => 'AA',
                    'message_id' => $hl7MessageId,
                    'transport' => 'simulated-hl7-v2',
                    'request' => $requestPayload,
                ],
            ]);
        } catch (Throwable $throwable) {
            $integration->update([
                'status' => ExternalIntegration::STATUS_FAILED,
                'error_message' => $throwable->getMessage(),
                'response_payload' => [
                    'ack' => 'AE',
                ],
            ]);

            throw $throwable;
        }
    }
}
