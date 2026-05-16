<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreDrugRequest;
use App\Models\PharmacyDrug;
use Illuminate\Http\JsonResponse;
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

        $query = PharmacyDrug::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->orderBy('trade_name');

        if ($lowStockOnly) {
            $query->whereColumn('current_stock', '<=', 'min_stock_level');
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('trade_name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%");
            });
        }

        $drugs = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $drugs->map(fn (PharmacyDrug $drug): array => [
                    'id' => $drug->id,
                    'trade_name' => $drug->trade_name,
                    'generic_name' => $drug->generic_name,
                    'unit_price' => (float) $drug->unit_price,
                    'min_stock_level' => (int) $drug->min_stock_level,
                    'current_stock' => (int) $drug->current_stock,
                    'is_low_stock' => (int) $drug->current_stock <= (int) $drug->min_stock_level,
                ])->values(),
            ]);
        }

        return Inertia::render('pharmacy/Drugs/Index', [
            'drugs' => $drugs,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'low_stock_only' => $lowStockOnly,
            ],
        ]);
    }

    public function store(StoreDrugRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $drug = PharmacyDrug::query()->create([
            'clinic_id' => $clinicId,
            'trade_name' => $payload['trade_name'],
            'generic_name' => $payload['generic_name'],
            'dosage_form' => $payload['dosage_form'] ?? null,
            'strength' => $payload['strength'] ?? null,
            'supplier_name' => $payload['supplier_name'] ?? null,
            'unit_price' => $payload['unit_price'] ?? 0,
            'min_stock_level' => $payload['min_stock_level'] ?? 0,
            'current_stock' => $payload['current_stock'] ?? 0,
            'expires_at' => $payload['expires_at'] ?? null,
            'is_active' => $payload['is_active'] ?? true,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.drugs.create',
            auditable: $drug,
            newValues: $drug->only(['trade_name', 'generic_name', 'unit_price', 'min_stock_level', 'current_stock']),
        );

        return response()->json([
            'data' => [
                'id' => $drug->id,
                'trade_name' => $drug->trade_name,
                'generic_name' => $drug->generic_name,
                'unit_price' => (float) $drug->unit_price,
                'min_stock_level' => (int) $drug->min_stock_level,
                'current_stock' => (int) $drug->current_stock,
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
