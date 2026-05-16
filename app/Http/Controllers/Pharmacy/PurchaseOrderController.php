<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\ReceivePurchaseOrderRequest;
use App\Http\Requests\Pharmacy\StorePurchaseOrderRequest;
use App\Models\PharmacyDrug;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $purchaseOrders = PurchaseOrder::query()
            ->forClinic($clinicId)
            ->with(['supplier:id,name', 'items:id,purchase_order_id,medication_name,quantity_ordered,quantity_received'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier' => $purchaseOrder->supplier?->name,
                'status' => $purchaseOrder->status,
                'ordered_at' => $purchaseOrder->ordered_at?->toISOString(),
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'received_at' => $purchaseOrder->received_at?->toISOString(),
                'subtotal_amount' => (float) $purchaseOrder->subtotal_amount,
                'total_amount' => (float) $purchaseOrder->total_amount,
                'items_count' => $purchaseOrder->items->count(),
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->id,
                    'medication_name' => $item->medication_name,
                    'quantity_ordered' => (int) $item->quantity_ordered,
                    'quantity_received' => (int) $item->quantity_received,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $supplier = Supplier::query()
            ->forClinic($clinicId)
            ->whereKey((int) $payload['supplier_id'])
            ->firstOrFail();

        $purchaseOrder = DB::transaction(function () use ($clinicId, $request, $payload, $supplier): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->create([
                'clinic_id' => $clinicId,
                'supplier_id' => $supplier->id,
                'ordered_by' => $request->user()?->id,
                'po_number' => $payload['po_number'] ?? $this->generatePoNumber($clinicId),
                'status' => PurchaseOrder::STATUS_ORDERED,
                'ordered_at' => now(),
                'expected_at' => $payload['expected_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'subtotal_amount' => 0,
                'total_amount' => 0,
            ]);

            $subtotal = 0.0;

            foreach ($payload['items'] as $item) {
                $quantityOrdered = (int) $item['quantity_ordered'];
                $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : 0.0;
                $lineTotal = round($quantityOrdered * $unitCost, 2);
                $subtotal += $lineTotal;

                PurchaseOrderItem::query()->create([
                    'clinic_id' => $clinicId,
                    'purchase_order_id' => $purchaseOrder->id,
                    'pharmacy_drug_id' => $item['pharmacy_drug_id'] ?? null,
                    'medication_name' => $item['medication_name'],
                    'quantity_ordered' => $quantityOrdered,
                    'quantity_received' => 0,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $purchaseOrder->subtotal_amount = round($subtotal, 2);
            $purchaseOrder->total_amount = round($subtotal, 2);
            $purchaseOrder->save();

            return $purchaseOrder->load('items');
        });

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.purchase_orders.create',
            auditable: $purchaseOrder,
            newValues: [
                'po_number' => $purchaseOrder->po_number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'items_count' => $purchaseOrder->items->count(),
                'total_amount' => (float) $purchaseOrder->total_amount,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'supplier_id' => $purchaseOrder->supplier_id,
                'items_count' => $purchaseOrder->items->count(),
                'total_amount' => (float) $purchaseOrder->total_amount,
            ],
        ], Response::HTTP_CREATED);
    }

    public function receive(ReceivePurchaseOrderRequest $request, int $purchaseOrderId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($clinicId, $request, $purchaseOrderId, $payload): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->forClinic($clinicId)
                ->with(['supplier', 'items'])
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($payload['items'] as $row) {
                $item = $purchaseOrder->items->firstWhere('id', (int) $row['item_id']);

                if ($item === null) {
                    continue;
                }

                $quantityReceived = (int) $row['quantity_received'];
                $item->quantity_received = (int) $item->quantity_received + $quantityReceived;
                $item->save();

                $drug = $this->resolveDrugForPurchaseItem(
                    clinicId: $clinicId,
                    purchaseOrder: $purchaseOrder,
                    item: $item,
                );

                $drug->current_stock = (int) $drug->current_stock + $quantityReceived;
                $drug->save();
            }

            $purchaseOrder->received_by = $request->user()?->id;
            $purchaseOrder->notes = $payload['notes'] ?? $purchaseOrder->notes;
            $purchaseOrder->received_at = now();
            $purchaseOrder->status = $purchaseOrder->items->every(
                fn (PurchaseOrderItem $item): bool => (int) $item->quantity_received >= (int) $item->quantity_ordered,
            ) ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
            $purchaseOrder->save();

            return $purchaseOrder->refresh()->load('items');
        });

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.purchase_orders.receive',
            auditable: $purchaseOrder,
            metadata: [
                'items_received' => count($payload['items']),
                'status' => $purchaseOrder->status,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'received_at' => $purchaseOrder->received_at?->toISOString(),
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

    private function generatePoNumber(int $clinicId): string
    {
        $today = now()->toDateString();
        $sequence = (int) PurchaseOrder::query()
            ->forClinic($clinicId)
            ->whereDate('created_at', $today)
            ->count() + 1;

        return sprintf('PO-%s-%04d', now()->format('Ymd'), $sequence);
    }

    private function resolveDrugForPurchaseItem(
        int $clinicId,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
    ): PharmacyDrug {
        if ($item->pharmacy_drug_id !== null) {
            return PharmacyDrug::query()
                ->forClinic($clinicId)
                ->whereKey($item->pharmacy_drug_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $drug = PharmacyDrug::query()->create([
            'clinic_id' => $clinicId,
            'trade_name' => $item->medication_name,
            'generic_name' => $item->medication_name,
            'supplier_name' => $purchaseOrder->supplier?->name,
            'unit_price' => (float) $item->unit_cost,
            'min_stock_level' => 0,
            'current_stock' => 0,
            'is_active' => true,
        ]);

        $item->pharmacy_drug_id = $drug->id;
        $item->save();

        return $drug;
    }
}
