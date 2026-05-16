<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\InventoryAlert;
use App\Models\PharmacyDrug;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PharmacyDashboardController extends Controller
{
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $summary = [
            'drugs_total' => PharmacyDrug::query()
                ->forClinic($clinicId)
                ->where('is_active', true)
                ->count(),
            'low_stock_total' => PharmacyDrug::query()
                ->forClinic($clinicId)
                ->where('is_active', true)
                ->whereColumn('current_stock', '<=', 'min_stock_level')
                ->count(),
            'open_alerts_total' => InventoryAlert::query()
                ->forClinic($clinicId)
                ->open()
                ->count(),
            'pending_purchase_orders_total' => PurchaseOrder::query()
                ->forClinic($clinicId)
                ->whereIn('status', [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->count(),
        ];

        $lowStockItems = PharmacyDrug::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->orderBy('current_stock')
            ->orderBy('trade_name')
            ->limit(20)
            ->get(['id', 'trade_name', 'generic_name', 'current_stock', 'min_stock_level', 'expires_at']);

        $recentAlerts = InventoryAlert::query()
            ->forClinic($clinicId)
            ->with('pharmacyDrug:id,trade_name,generic_name')
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get();

        $recentPurchaseOrders = PurchaseOrder::query()
            ->forClinic($clinicId)
            ->with('supplier:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'supplier_id', 'po_number', 'status', 'ordered_at', 'expected_at', 'received_at', 'total_amount']);

        $payload = [
            'summary' => $summary,
            'low_stock_items' => $lowStockItems->map(fn (PharmacyDrug $drug): array => [
                'id' => $drug->id,
                'trade_name' => $drug->trade_name,
                'generic_name' => $drug->generic_name,
                'current_stock' => (int) $drug->current_stock,
                'min_stock_level' => (int) $drug->min_stock_level,
                'expires_at' => $drug->expires_at?->toDateString(),
            ])->values(),
            'recent_alerts' => $recentAlerts->map(fn (InventoryAlert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'message' => $alert->message,
                'drug_name' => $alert->pharmacyDrug?->trade_name ?? $alert->pharmacyDrug?->generic_name,
                'detected_at' => $alert->detected_at?->toISOString(),
                'resolved_at' => $alert->resolved_at?->toISOString(),
            ])->values(),
            'recent_purchase_orders' => $recentPurchaseOrders->map(fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier_name' => $purchaseOrder->supplier?->name,
                'status' => $purchaseOrder->status,
                'ordered_at' => $purchaseOrder->ordered_at?->toISOString(),
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'received_at' => $purchaseOrder->received_at?->toISOString(),
                'total_amount' => (float) $purchaseOrder->total_amount,
            ])->values(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('pharmacy/Index', $payload);
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
