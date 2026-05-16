<?php

namespace App\Http\Controllers\Radiology;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Radiology\StoreRadiologyImageRequest;
use App\Jobs\DispatchRadiologyReportToPacsJob;
use App\Models\ExternalIntegration;
use App\Models\RadiologyImage;
use App\Models\RadiologyOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RadiologyImageController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function store(StoreRadiologyImageRequest $request, int $radiologyOrderId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $order = RadiologyOrder::query()
            ->forClinic($clinicId)
            ->whereKey($radiologyOrderId)
            ->firstOrFail();

        $uploadedFile = $request->file('image');

        if ($uploadedFile === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Radiology image file is required.');
        }

        $disk = 'public';
        $storedPath = $uploadedFile->store(
            sprintf('radiology-images/%d/%d', $clinicId, $order->id),
            $disk,
        );

        $image = RadiologyImage::query()->create([
            'clinic_id' => $clinicId,
            'radiology_order_id' => $order->id,
            'uploaded_by' => $request->user()?->id,
            'dicom_uid' => $payload['dicom_uid'] ?? Str::uuid()->toString(),
            'file_disk' => $disk,
            'file_path' => $storedPath,
            'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $uploadedFile->getSize(),
            'captured_at' => $payload['captured_at'] ?? now(),
            'metadata' => $payload['metadata'] ?? null,
        ]);

        if ($order->status === RadiologyOrder::STATUS_ORDERED) {
            $order->status = RadiologyOrder::STATUS_COMPLETED;
            $order->save();
        }

        $integration = ExternalIntegration::query()->create([
            'clinic_id' => $clinicId,
            'created_by' => $request->user()?->id,
            'integration_type' => ExternalIntegration::TYPE_PACS,
            'reference_type' => $image::class,
            'reference_id' => $image->id,
            'status' => ExternalIntegration::STATUS_QUEUED,
            'request_payload' => [
                'radiology_order_id' => $order->id,
                'dicom_uid' => $image->dicom_uid,
                'file_path' => $image->file_path,
                'mime_type' => $image->mime_type,
                'size_bytes' => (int) $image->size_bytes,
            ],
        ]);

        DispatchRadiologyReportToPacsJob::dispatch($integration->id);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'radiology.images.upload',
            auditable: $order,
            metadata: [
                'radiology_image_id' => $image->id,
                'integration_id' => $integration->id,
                'file_path' => $storedPath,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $image->id,
                'radiology_order_id' => $image->radiology_order_id,
                'dicom_uid' => $image->dicom_uid,
                'file_url' => Storage::disk($disk)->url($storedPath),
                'captured_at' => $image->captured_at?->toISOString(),
                'integration_id' => $integration->id,
            ],
        ], Response::HTTP_CREATED);
    }

    private function resolveClinicId(StoreRadiologyImageRequest $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
