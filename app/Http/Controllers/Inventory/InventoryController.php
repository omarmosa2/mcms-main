<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStockAction;
use App\Actions\Inventory\ConsumeFromBatchAction;
use App\Actions\Inventory\CreateDrugBatchAction;
use App\Actions\Inventory\ProcessReturnAction;
use App\Http\Controllers\Controller;
use App\Models\DrugBatch;
use App\Models\InventoryReturn;
use App\Models\StockAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InventoryController extends Controller
{
    public function __construct(
        private AdjustStockAction $adjustStockAction,
        private CreateDrugBatchAction $createDrugBatchAction,
        private ConsumeFromBatchAction $consumeFromBatchAction,
        private ProcessReturnAction $processReturnAction,
    ) {}

    public function adjustStock(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'drug_id' => ['required', 'integer'],
            'quantity_change' => ['required', 'integer'],
            'reason' => ['required', 'string', 'in:count_correction,damaged,expired,received,returned,other'],
            'notes' => ['nullable', 'string'],
        ]);

        $adjustment = $this->adjustStockAction->handle(
            clinicId: $clinicId,
            userId: $user->id,
            drugId: $validated['drug_id'],
            quantityChange: $validated['quantity_change'],
            reason: $validated['reason'],
            notes: $validated['notes'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $adjustment], 201);
        }

        return redirect()->back()->with('toast', ['message' => 'Stock adjusted successfully.', 'type' => 'success']);
    }

    public function createBatch(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'drug_id' => ['required', 'integer'],
            'batch_number' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expiry_date' => ['required', 'date', 'after:today'],
        ]);

        $batch = $this->createDrugBatchAction->handle(
            clinicId: $clinicId,
            userId: $user->id,
            drugId: $validated['drug_id'],
            batchNumber: $validated['batch_number'],
            quantity: $validated['quantity'],
            expiryDate: $validated['expiry_date'],
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $batch], 201);
        }

        return redirect()->back()->with('toast', ['message' => 'Drug batch created.', 'type' => 'success']);
    }

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        return $this->batches($request);
    }

    public function batches(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'expiry_date');
        $sortDirection = $request->get('sort_direction', 'asc');

        $query = DrugBatch::query()
            ->forClinic($clinicId)
            ->with(['drug:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('drug', function ($dq) use ($search): void {
                        $dq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->boolean('not_expired')) {
            $query->notExpired();
        }

        if ($request->boolean('available')) {
            $query->withAvailableStock();
        }

        $batches = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $batches]);
        }

        return Inertia::render('inventory/Batches/Index', [
            'batches' => $batches,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function consumeBatch(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'drug_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $consumedBatches = $this->consumeFromBatchAction->handle(
            clinicId: $clinicId,
            userId: $user->id,
            drugId: $validated['drug_id'],
            quantity: $validated['quantity'],
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $consumedBatches]);
        }

        return redirect()->back()->with('toast', ['message' => 'Stock consumed from batches.', 'type' => 'success']);
    }

    public function returnStock(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $validated = $request->validate([
            'drug_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:expired,damaged,wrong_order,quality_issue,other'],
            'returned_to_supplier' => ['boolean'],
            'supplier_id' => ['nullable', 'integer'],
            'batch_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $return = $this->processReturnAction->handle(
            clinicId: $clinicId,
            userId: $user->id,
            drugId: $validated['drug_id'],
            quantity: $validated['quantity'],
            reason: $validated['reason'],
            returnedToSupplier: $validated['returned_to_supplier'] ?? false,
            supplierId: $validated['supplier_id'] ?? null,
            batchId: $validated['batch_id'] ?? null,
            notes: $validated['notes'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $return], 201);
        }

        return redirect()->back()->with('toast', ['message' => 'Inventory return processed.', 'type' => 'success']);
    }

    public function adjustments(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'adjusted_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = StockAdjustment::query()
            ->forClinic($clinicId)
            ->with(['drug:id,clinic_id,name', 'adjustedBy:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('drug', function ($dq) use ($search): void {
                        $dq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('reason')) {
            $query->where('reason', $request->get('reason'));
        }

        $adjustments = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $adjustments]);
        }

        return Inertia::render('inventory/Adjustments/Index', [
            'adjustments' => $adjustments,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function returns(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $sortBy = $request->get('sort_by', 'returned_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = InventoryReturn::query()
            ->forClinic($clinicId)
            ->with(['drug:id,clinic_id,name', 'supplier:id,clinic_id,name'])
            ->orderBy($sortBy, $sortDirection);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('drug', function ($dq) use ($search): void {
                        $dq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $returns = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $returns]);
        }

        return Inertia::render('inventory/Returns/Index', [
            'returns' => $returns,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }
}
