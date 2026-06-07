<?php

namespace App\Http\Controllers\MedicalRecords;

use App\Actions\MedicalRecords\StoreFollowUpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalRecords\StoreFollowUpRequest;
use App\Http\Resources\FollowUpResource;
use App\Models\FollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FollowUpController extends Controller
{
    public function __construct(private StoreFollowUpAction $storeFollowUpAction) {}

    public function store(StoreFollowUpRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $followUp = $this->storeFollowUpAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        return FollowUpResource::make($followUp->load('doctor:id,clinic_id,name'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    public function update(Request $request, int $followUpId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'follow_up_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recommended_action' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled,missed'],
        ]);

        $followUp = FollowUp::query()
            ->forClinic($clinicId)
            ->whereKey($followUpId)
            ->firstOrFail();

        $followUp->update(array_merge($validated, ['updated_by' => $request->user()->id]));

        return FollowUpResource::make($followUp->fresh()->load('doctor:id,clinic_id,name'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_OK);
    }

    public function destroy(Request $request, int $followUpId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $followUp = FollowUp::query()
            ->forClinic($clinicId)
            ->whereKey($followUpId)
            ->firstOrFail();

        $followUp->delete();

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
