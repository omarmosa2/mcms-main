<?php

namespace App\Http\Controllers\Radiology;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Radiology\StoreRadiologyReportRequest;
use App\Jobs\DispatchRadiologyReportToPacsJob;
use App\Models\ExternalIntegration;
use App\Models\RadiologyOrder;
use App\Models\RadiologyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class RadiologyReportController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = RadiologyReport::query()
            ->forClinic($clinicId)
            ->with(['order:id,clinic_id,study_name,patient_id', 'order.patient:id,clinic_id,full_name', 'reporter:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('findings', 'like', "%{$search}%")
                    ->orWhere('impression', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search): void {
                        $oq->where('study_name', 'like', "%{$search}%")
                            ->orWhereHas('patient', function ($pq) use ($search): void {
                                $pq->where('full_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $reports = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $reports]);
        }

        return Inertia::render('radiology/Reports/Index', [
            'reports' => $reports,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function store(StoreRadiologyReportRequest $request, int $radiologyOrderId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $order = RadiologyOrder::query()
            ->forClinic($clinicId)
            ->whereKey($radiologyOrderId)
            ->firstOrFail();

        $report = RadiologyReport::query()->create([
            'clinic_id' => $clinicId,
            'radiology_order_id' => $order->id,
            'reported_by' => $request->user()?->id,
            'findings' => $payload['findings'],
            'impression' => $payload['impression'] ?? null,
            'reported_at' => $payload['reported_at'] ?? now(),
        ]);

        $order->status = RadiologyOrder::STATUS_REPORTED;
        $order->save();

        $integration = ExternalIntegration::query()->create([
            'clinic_id' => $clinicId,
            'created_by' => $request->user()?->id,
            'integration_type' => ExternalIntegration::TYPE_PACS,
            'reference_type' => $report::class,
            'reference_id' => $report->id,
            'status' => ExternalIntegration::STATUS_QUEUED,
            'request_payload' => [
                'radiology_order_id' => $order->id,
                'study_name' => $order->study_name,
                'patient_id' => $order->patient_id,
                'findings' => $report->findings,
                'impression' => $report->impression,
                'reported_at' => $report->reported_at?->toISOString(),
            ],
        ]);

        DispatchRadiologyReportToPacsJob::dispatch($integration->id);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'radiology.reports.create',
            auditable: $order,
            newValues: [
                'report_id' => $report->id,
                'status' => $order->status,
                'integration_id' => $integration->id,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $report->id,
                'radiology_order_id' => $report->radiology_order_id,
                'findings' => $report->findings,
                'impression' => $report->impression,
                'reported_at' => $report->reported_at?->toISOString(),
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
