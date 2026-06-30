<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreDrugRequest;
use App\Http\Requests\Pharmacy\UpdateDrugRequest;
use App\Models\DrugBatch;
use App\Models\PharmacyDrug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DrugController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        $clinicId = $this->resolveClinicId($request);
        $lowStockOnly = $request->boolean('low_stock_only');
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $category = $request->get('category');
        $form = $request->get('form');
        $stockStatus = $request->get('stock_status');

        $query = PharmacyDrug::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->withCount('batches')
            ->orderBy('trade_name');

        if ($lowStockOnly) {
            $query->whereColumn('current_stock', '<=', 'min_stock_level');
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('trade_name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($form) {
            $query->where('form', $form);
        }

        if ($stockStatus === 'low') {
            $query->whereColumn('current_stock', '<=', 'min_stock_level')
                ->whereColumn('current_stock', '>', 0);
        } elseif ($stockStatus === 'out') {
            $query->where('current_stock', 0);
        } elseif ($stockStatus === 'available') {
            $query->whereColumn('current_stock', '>', 'min_stock_level');
        }

        $drugs = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $drugs->map(fn (PharmacyDrug $drug): array => [
                    'id' => $drug->id,
                    'trade_name' => $drug->trade_name,
                    'generic_name' => $drug->generic_name,
                    'code' => $drug->code,
                    'barcode' => $drug->barcode,
                    'category' => $drug->category,
                    'form' => $drug->form,
                    'unit' => $drug->unit,
                    'strength' => $drug->strength,
                    'manufacturer' => $drug->manufacturer,
                    'unit_price' => (float) $drug->unit_price,
                    'min_stock_level' => (int) $drug->min_stock_level,
                    'current_stock' => (int) $drug->current_stock,
                    'is_low_stock' => $drug->isLowStock(),
                    'expires_at' => $drug->expires_at?->toDateString(),
                    'nearest_expiry' => $drug->nearestExpiryDate(),
                ])->values(),
            ]);
        }

        return Inertia::render('pharmacy/Drugs/Index', [
            'drugs' => $drugs,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'low_stock_only' => $lowStockOnly,
                'category' => $category,
                'form' => $form,
                'stock_status' => $stockStatus,
            ],
        ]);
    }

    public function store(StoreDrugRequest $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $drug = PharmacyDrug::query()->create([
            'clinic_id' => $clinicId,
            'trade_name' => $payload['trade_name'],
            'generic_name' => $payload['generic_name'],
            'code' => $payload['code'] ?? null,
            'barcode' => $payload['barcode'] ?? null,
            'category' => $payload['category'] ?? null,
            'form' => $payload['form'] ?? null,
            'unit' => $payload['unit'] ?? null,
            'strength' => $payload['strength'] ?? null,
            'manufacturer' => $payload['manufacturer'] ?? null,
            'description' => $payload['description'] ?? null,
            'dosage_form' => $payload['dosage_form'] ?? $payload['form'] ?? null,
            'supplier_name' => $payload['supplier_name'] ?? null,
            'unit_price' => $payload['unit_price'] ?? 0,
            'min_stock_level' => $payload['min_stock_level'] ?? 0,
            'current_stock' => $payload['current_stock'] ?? 0,
            'expires_at' => $payload['expires_at'] ?? null,
            'is_active' => $payload['is_active'] ?? true,
            'created_by' => $request->user()?->id,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.drugs.create',
            auditable: $drug,
            newValues: $drug->only(['trade_name', 'generic_name', 'code', 'unit_price', 'min_stock_level', 'current_stock']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إضافة الدواء بنجاح.']);

        return redirect()->back();
    }

    public function update(UpdateDrugRequest $request, int $drugId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $drug = PharmacyDrug::query()
            ->forClinic($clinicId)
            ->whereKey($drugId)
            ->firstOrFail();

        $oldValues = $drug->only(['trade_name', 'generic_name', 'code', 'unit_price', 'min_stock_level', 'current_stock', 'is_active']);

        $drug->fill($payload);
        $drug->updated_by = $request->user()?->id;
        $drug->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.drugs.update',
            auditable: $drug,
            oldValues: $oldValues,
            newValues: $drug->only(['trade_name', 'generic_name', 'code', 'unit_price', 'min_stock_level', 'current_stock', 'is_active']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث الدواء بنجاح.']);

        return redirect()->back();
    }

    public function destroy(Request $request, int $drugId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $drug = PharmacyDrug::query()
            ->forClinic($clinicId)
            ->whereKey($drugId)
            ->firstOrFail();

        if ($drug->stockMovements()->exists()) {
            $drug->is_active = false;
            $drug->save();

            Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تعطيل الدواء (لا يمكن حذفه لوجود حركات مخزون).']);

            return redirect()->back();
        }

        $drug->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.drugs.delete',
            auditable: $drug,
            oldValues: $drug->only(['trade_name', 'generic_name']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف الدواء بنجاح.']);

        return redirect()->back();
    }

    public function batches(Request $request, int $drugId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $batches = DrugBatch::query()
            ->forClinic($clinicId)
            ->where('pharmacy_drug_id', $drugId)
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'data' => $batches->map(fn (DrugBatch $batch): array => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => (int) $batch->quantity,
                'initial_quantity' => (int) $batch->initial_quantity,
                'purchase_price' => $batch->purchase_price ? (float) $batch->purchase_price : null,
                'selling_price' => $batch->selling_price ? (float) $batch->selling_price : null,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'received_at' => $batch->received_at?->toISOString(),
                'supplier_name' => $batch->supplier_name,
                'is_expired' => $batch->isExpired(),
                'remaining_days' => $batch->remainingDays(),
            ])->values(),
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
