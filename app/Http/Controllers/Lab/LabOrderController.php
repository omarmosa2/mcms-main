<?php

namespace App\Http\Controllers\Lab;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabOrderRequest;
use App\Models\LabOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LabOrderController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = LabOrder::query()
            ->forClinic($clinicId)
            ->with(['patient:id,clinic_id,first_name,last_name,full_name', 'visit:id,clinic_id,visit_number', 'orderer:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('test_name', 'like', "%{$search}%")
                    ->orWhere('test_code', 'like', "%{$search}%")
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

        return Inertia::render('lab/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function store(StoreLabOrderRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $order = LabOrder::query()->create([
            'clinic_id' => $clinicId,
            'visit_id' => $payload['visit_id'] ?? null,
            'patient_id' => (int) $payload['patient_id'],
            'ordered_by' => $request->user()?->id,
            'test_code' => $payload['test_code'] ?? null,
            'test_name' => $payload['test_name'],
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'lab.orders.create',
            auditable: $order,
            newValues: $order->only(['id', 'test_name', 'status']),
        );

        return response()->json([
            'data' => [
                'id' => $order->id,
                'patient_id' => $order->patient_id,
                'visit_id' => $order->visit_id,
                'test_name' => $order->test_name,
                'status' => $order->status,
                'ordered_at' => $order->ordered_at?->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }

    private function resolveClinicId(StoreLabOrderRequest $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
