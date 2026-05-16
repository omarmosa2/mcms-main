<?php

namespace App\Jobs;

use App\Models\ExternalIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchRadiologyReportToPacsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $integrationId) {}

    public function handle(): void
    {
        $integration = ExternalIntegration::query()->find($this->integrationId);

        if ($integration === null || $integration->integration_type !== ExternalIntegration::TYPE_PACS) {
            return;
        }

        try {
            $requestPayload = is_array($integration->request_payload) ? $integration->request_payload : [];
            $studyId = sprintf('PACS-STUDY-%d', $integration->id);
            $instanceId = sprintf('PACS-INSTANCE-%s', now()->format('YmdHis'));

            $integration->update([
                'status' => ExternalIntegration::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'response_payload' => [
                    'ack' => 'accepted',
                    'study_id' => $studyId,
                    'instance_id' => $instanceId,
                    'transport' => 'simulated-dicomweb',
                    'request' => $requestPayload,
                ],
            ]);
        } catch (Throwable $throwable) {
            $integration->update([
                'status' => ExternalIntegration::STATUS_FAILED,
                'error_message' => $throwable->getMessage(),
                'response_payload' => [
                    'ack' => 'failed',
                ],
            ]);

            throw $throwable;
        }
    }
}
