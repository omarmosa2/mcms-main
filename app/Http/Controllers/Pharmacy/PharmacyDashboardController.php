<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\DrugBatch;
use App\Models\InventoryAlert;
use App\Models\PharmacyDrug;
use App\Models\Prescription;
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

        $today = now()->toDateString();

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
            'expired_drugs_total' => DrugBatch::query()
                ->forClinic($clinicId)
                ->where('expiry_date', '<=', now())
                ->where('quantity', '>', 0)
                ->count(),
            'near_expiry_total' => DrugBatch::query()
                ->forClinic($clinicId)
                ->where('expiry_date', '>', now())
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('quantity', '>', 0)
                ->count(),
            'prescriptions_today' => Prescription::query()
                ->forClinic($clinicId)
                ->whereDate('created_at', $today)
                ->count(),
            'prescriptions_pending' => Prescription::query()
                ->forClinic($clinicId)
                ->whereIn('status', [
                    Prescription::STATUS_SENT_TO_PHARMACY,
                    Prescription::STATUS_RECEIVED,
                    Prescription::STATUS_PREPARING,
                    Prescription::STATUS_READY,
                ])
                ->count(),
            'prescriptions_preparing' => Prescription::query()
                ->forClinic($clinicId)
                ->where('status', Prescription::STATUS_PREPARING)
                ->count(),
            'prescriptions_ready' => Prescription::query()
                ->forClinic($clinicId)
                ->where('status', Prescription::STATUS_READY)
                ->count(),
            'prescriptions_dispensed_today' => Prescription::query()
                ->forClinic($clinicId)
                ->where('status', Prescription::STATUS_DISPENSED)
                ->whereDate('dispensed_at', $today)
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
            ->get(['id', 'trade_name', 'generic_name', 'current_stock', 'min_stock_level', 'expires_at', 'code', 'form', 'unit']);

        $recentAlerts = InventoryAlert::query()
            ->forClinic($clinicId)
            ->with('pharmacyDrug:id,trade_name,generic_name')
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get();

        $recentPrescriptions = Prescription::query()
            ->forClinic($clinicId)
            ->whereIn('status', Prescription::PHARMACY_STATUSES)
            ->with(['patient:id,clinic_id,first_name,last_name,full_name', 'prescriber:id,clinic_id,name', 'items'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $nearExpiryBatches = DrugBatch::query()
            ->forClinic($clinicId)
            ->with('drug:id,trade_name,generic_name')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->limit(20)
            ->get();

        $drugs = PharmacyDrug::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'trade_name', 'generic_name', 'code', 'form', 'unit', 'strength', 'current_stock', 'min_stock_level', 'unit_price', 'manufacturer']);

        $payload = [
            'summary' => $summary,
            'low_stock_items' => $lowStockItems->map(fn (PharmacyDrug $drug): array => [
                'id' => $drug->id,
                'trade_name' => $drug->trade_name,
                'generic_name' => $drug->generic_name,
                'code' => $drug->code,
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
            'recent_prescriptions' => $recentPrescriptions->map(fn (Prescription $rx): array => [
                'id' => $rx->id,
                'prescription_number' => $rx->prescription_number,
                'status' => $rx->status,
                'patient_name' => $rx->patient?->full_name ?? $rx->patient?->first_name,
                'doctor_name' => $rx->prescriber?->name,
                'items_count' => $rx->items->count(),
                'created_at' => $rx->created_at?->toISOString(),
                'sent_to_pharmacy_at' => $rx->sent_to_pharmacy_at?->toISOString(),
            ])->values(),
            'near_expiry_batches' => $nearExpiryBatches->map(fn (DrugBatch $batch): array => [
                'id' => $batch->id,
                'drug_name' => $batch->drug?->trade_name,
                'batch_number' => $batch->batch_number,
                'quantity' => (int) $batch->quantity,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'remaining_days' => $batch->remainingDays(),
            ])->values(),
            'drugs' => $drugs->map(fn (PharmacyDrug $drug): array => [
                'id' => $drug->id,
                'trade_name' => $drug->trade_name,
                'generic_name' => $drug->generic_name,
                'code' => $drug->code,
                'form' => $drug->form,
                'unit' => $drug->unit,
                'strength' => $drug->strength,
                'manufacturer' => $drug->manufacturer,
                'current_stock' => (int) $drug->current_stock,
                'min_stock_level' => (int) $drug->min_stock_level,
                'unit_price' => (float) $drug->unit_price,
                'is_low_stock' => $drug->isLowStock(),
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
