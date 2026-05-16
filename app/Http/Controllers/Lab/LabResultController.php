<?php

namespace App\Http\Controllers\Lab;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabResultRequest;
use App\Jobs\DispatchLabResultToLisJob;
use App\Models\ExternalIntegration;
use App\Models\LabOrder;
use App\Models\LabResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LabResultController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = LabResult::query()
            ->forClinic($clinicId)
            ->with(['order:id,clinic_id,test_name,patient_id', 'order.patient:id,clinic_id,full_name', 'resulter:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('result_value', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search): void {
                        $oq->where('test_name', 'like', "%{$search}%")
                            ->orWhereHas('patient', function ($pq) use ($search): void {
                                $pq->where('full_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $results = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $results]);
        }

        return Inertia::render('lab/Results/Index', [
            'results' => $results,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function store(StoreLabResultRequest $request, int $labOrderId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $order = LabOrder::query()
            ->forClinic($clinicId)
            ->whereKey($labOrderId)
            ->firstOrFail();

        $result = LabResult::query()->create([
            'clinic_id' => $clinicId,
            'lab_order_id' => $order->id,
            'resulted_by' => $request->user()?->id,
            'result_value' => $payload['result_value'] ?? null,
            'unit' => $payload['unit'] ?? null,
            'reference_range' => $payload['reference_range'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'resulted_at' => $payload['resulted_at'] ?? now(),
        ]);

        $order->status = LabOrder::STATUS_RESULTED;
        $order->save();

        $integration = ExternalIntegration::query()->create([
            'clinic_id' => $clinicId,
            'created_by' => $request->user()?->id,
            'integration_type' => ExternalIntegration::TYPE_LIS_HL7,
            'reference_type' => $result::class,
            'reference_id' => $result->id,
            'status' => ExternalIntegration::STATUS_QUEUED,
            'request_payload' => [
                'lab_order_id' => $order->id,
                'test_name' => $order->test_name,
                'patient_id' => $order->patient_id,
                'result_value' => $result->result_value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'resulted_at' => $result->resulted_at?->toISOString(),
            ],
        ]);

        DispatchLabResultToLisJob::dispatch($integration->id);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'lab.results.create',
            auditable: $order,
            newValues: [
                'result_id' => $result->id,
                'status' => $order->status,
                'integration_id' => $integration->id,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $result->id,
                'lab_order_id' => $result->lab_order_id,
                'result_value' => $result->result_value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'resulted_at' => $result->resulted_at?->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }

    private function resolveClinicId(StoreLabResultRequest $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
