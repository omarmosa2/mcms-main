<?php

namespace App\Http\Controllers\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visits\StoreVisitVitalSignRequest;
use App\Models\Visit;
use App\Models\VisitVitalSign;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VisitVitalSignController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function store(StoreVisitVitalSignRequest $request, int $visitId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $visit = Visit::query()
            ->forClinic($clinicId)
            ->whereKey($visitId)
            ->firstOrFail();

        $payload = $request->validated();

        $vitalSign = VisitVitalSign::query()->create([
            'clinic_id' => $clinicId,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'recorded_by' => $request->user()?->id,
            'systolic_bp' => $payload['systolic_bp'] ?? null,
            'diastolic_bp' => $payload['diastolic_bp'] ?? null,
            'heart_rate' => $payload['heart_rate'] ?? null,
            'respiratory_rate' => $payload['respiratory_rate'] ?? null,
            'oxygen_saturation' => $payload['oxygen_saturation'] ?? null,
            'temperature_celsius' => $payload['temperature_celsius'] ?? null,
            'weight_kg' => $payload['weight_kg'] ?? null,
            'height_cm' => $payload['height_cm'] ?? null,
            'recorded_at' => $payload['recorded_at'] ?? now(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'visits.vitals.create',
            auditable: $visit,
            newValues: $vitalSign->only([
                'id',
                'systolic_bp',
                'diastolic_bp',
                'heart_rate',
                'temperature_celsius',
                'oxygen_saturation',
            ]),
        );

        return response()->json([
            'data' => [
                'id' => $vitalSign->id,
                'visit_id' => $vitalSign->visit_id,
                'patient_id' => $vitalSign->patient_id,
                'systolic_bp' => $vitalSign->systolic_bp,
                'diastolic_bp' => $vitalSign->diastolic_bp,
                'heart_rate' => $vitalSign->heart_rate,
                'respiratory_rate' => $vitalSign->respiratory_rate,
                'oxygen_saturation' => $vitalSign->oxygen_saturation,
                'temperature_celsius' => $vitalSign->temperature_celsius !== null ? (float) $vitalSign->temperature_celsius : null,
                'weight_kg' => $vitalSign->weight_kg !== null ? (float) $vitalSign->weight_kg : null,
                'height_cm' => $vitalSign->height_cm !== null ? (float) $vitalSign->height_cm : null,
                'recorded_at' => $vitalSign->recorded_at?->toISOString(),
                'notes' => $vitalSign->notes,
            ],
        ], Response::HTTP_CREATED);
    }

    private function resolveClinicId(StoreVisitVitalSignRequest $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
