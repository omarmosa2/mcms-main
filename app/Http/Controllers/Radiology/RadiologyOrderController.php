<?php

namespace App\Http\Controllers\Radiology;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Radiology\StoreRadiologyOrderRequest;
use App\Models\RadiologyOrder;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class RadiologyOrderController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = RadiologyOrder::query()
            ->forClinic($clinicId)
            ->with(['patient:id,clinic_id,first_name,last_name,full_name', 'visit:id,clinic_id,visit_number', 'orderer:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('study_name', 'like', "%{$search}%")
                    ->orWhere('study_code', 'like', "%{$search}%")
                    ->orWhere('modality', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($pq) use ($search): void {
                        $pq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('file_number', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $orders]);
        }

        return Inertia::render('radiology/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function store(StoreRadiologyOrderRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        if (isset($payload['visit_id'])) {
            Visit::query()
                ->forClinic($clinicId)
                ->whereKey((int) $payload['visit_id'])
                ->firstOrFail();
        }

        $order = RadiologyOrder::query()->create([
            'clinic_id' => $clinicId,
            'visit_id' => $payload['visit_id'] ?? null,
            'patient_id' => (int) $payload['patient_id'],
            'ordered_by' => $request->user()?->id,
            'study_code' => $payload['study_code'] ?? null,
            'study_name' => $payload['study_name'],
            'modality' => $payload['modality'] ?? null,
            'status' => RadiologyOrder::STATUS_ORDERED,
            'ordered_at' => now(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'radiology.orders.create',
            auditable: $order,
            newValues: $order->only(['id', 'study_name', 'status']),
        );

        return response()->json([
            'data' => [
                'id' => $order->id,
                'patient_id' => $order->patient_id,
                'visit_id' => $order->visit_id,
                'study_name' => $order->study_name,
                'modality' => $order->modality,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at?->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
