<?php

namespace App\Http\Controllers\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visits\StoreVisitDiagnosisRequest;
use App\Models\Visit;
use App\Models\VisitDiagnosis;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VisitDiagnosisController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function store(StoreVisitDiagnosisRequest $request, int $visitId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $visit = Visit::query()
            ->forClinic($clinicId)
            ->whereKey($visitId)
            ->firstOrFail();

        $payload = $request->validated();

        if (($payload['is_primary'] ?? false) === true) {
            VisitDiagnosis::query()
                ->forClinic($clinicId)
                ->where('visit_id', $visit->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $diagnosis = VisitDiagnosis::query()->create([
            'clinic_id' => $clinicId,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'diagnosed_by' => $request->user()?->id,
            'icd10_code' => strtoupper((string) $payload['icd10_code']),
            'diagnosis_title' => $payload['diagnosis_title'] ?? null,
            'is_primary' => (bool) ($payload['is_primary'] ?? false),
            'notes' => $payload['notes'] ?? null,
            'diagnosed_at' => $payload['diagnosed_at'] ?? now(),
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'visits.diagnoses.create',
            auditable: $visit,
            newValues: $diagnosis->only([
                'id',
                'icd10_code',
                'diagnosis_title',
                'is_primary',
            ]),
        );

        return response()->json([
            'data' => [
                'id' => $diagnosis->id,
                'visit_id' => $diagnosis->visit_id,
                'patient_id' => $diagnosis->patient_id,
                'icd10_code' => $diagnosis->icd10_code,
                'diagnosis_title' => $diagnosis->diagnosis_title,
                'is_primary' => $diagnosis->is_primary,
                'notes' => $diagnosis->notes,
                'diagnosed_at' => $diagnosis->diagnosed_at?->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }

    private function resolveClinicId(StoreVisitDiagnosisRequest $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
