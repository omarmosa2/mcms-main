<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\ResolveInventoryAlertRequest;
use App\Models\InventoryAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InventoryAlertController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $status = $request->query('status');

        $query = InventoryAlert::query()
            ->forClinic($clinicId)
            ->with(['pharmacyDrug:id,trade_name,generic_name'])
            ->orderByDesc('detected_at');

        if (is_string($status) && in_array($status, [InventoryAlert::STATUS_OPEN, InventoryAlert::STATUS_RESOLVED], true)) {
            $query->where('status', $status);
        }

        $alerts = $query->limit(200)->get();

        return response()->json([
            'data' => $alerts->map(fn (InventoryAlert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'message' => $alert->message,
                'drug_name' => $alert->pharmacyDrug?->trade_name ?? $alert->pharmacyDrug?->generic_name,
                'detected_at' => $alert->detected_at?->toISOString(),
                'resolved_at' => $alert->resolved_at?->toISOString(),
                'metadata' => $alert->metadata,
            ])->values(),
        ]);
    }

    public function resolve(ResolveInventoryAlertRequest $request, int $alertId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $alert = InventoryAlert::query()
            ->forClinic($clinicId)
            ->whereKey($alertId)
            ->firstOrFail();

        if ($alert->status !== InventoryAlert::STATUS_RESOLVED) {
            $alert->status = InventoryAlert::STATUS_RESOLVED;
            $alert->resolved_by = $request->user()?->id;
            $alert->resolved_at = now();
            $alert->metadata = array_merge($alert->metadata ?? [], [
                'resolution_notes' => $payload['notes'] ?? null,
            ]);
            $alert->save();
        }

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.inventory_alerts.resolve',
            auditable: $alert,
            metadata: [
                'alert_type' => $alert->type,
                'severity' => $alert->severity,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $alert->id,
                'status' => $alert->status,
                'resolved_at' => $alert->resolved_at?->toISOString(),
            ],
        ]);
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
