<?php

namespace App\Http\Controllers\MedicalRecords;

use App\Actions\MedicalRecords\StoreTreatmentPlanAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalRecords\StoreTreatmentPlanRequest;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TreatmentPlanController extends Controller
{
    public function __construct(private StoreTreatmentPlanAction $storeTreatmentPlanAction) {}

    public function store(StoreTreatmentPlanRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $plan = $this->storeTreatmentPlanAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        return TreatmentPlanResource::make($plan->load('doctor:id,clinic_id,name'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    public function update(Request $request, int $planId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'in:new,in_progress,completed,cancelled'],
        ]);

        $plan = TreatmentPlan::query()
            ->forClinic($clinicId)
            ->whereKey($planId)
            ->firstOrFail();

        $plan->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return TreatmentPlanResource::make($plan->fresh()->load('doctor:id,clinic_id,name'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_OK);
    }

    public function destroy(Request $request, int $planId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $plan = TreatmentPlan::query()
            ->forClinic($clinicId)
            ->whereKey($planId)
            ->firstOrFail();

        $plan->delete();

        return response()->json(null, SymfonyResponse::HTTP_NO_CONTENT);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
