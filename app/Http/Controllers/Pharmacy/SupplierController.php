<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreSupplierRequest;
use App\Http\Requests\Pharmacy\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $suppliers = Supplier::query()
            ->forClinic($clinicId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $suppliers->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'is_active' => (bool) $supplier->is_active,
                'notes' => $supplier->notes,
            ])->values(),
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $supplier = Supplier::query()->create([
            'clinic_id' => $clinicId,
            'name' => $payload['name'],
            'contact_name' => $payload['contact_name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'address' => $payload['address'] ?? null,
            'is_active' => $payload['is_active'] ?? true,
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.suppliers.create',
            auditable: $supplier,
            newValues: $supplier->only(['name', 'contact_name', 'phone', 'email', 'is_active']),
        );

        return response()->json([
            'data' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'is_active' => (bool) $supplier->is_active,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateSupplierRequest $request, int $supplierId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $supplier = Supplier::query()
            ->forClinic($clinicId)
            ->whereKey($supplierId)
            ->firstOrFail();

        $oldValues = $supplier->only([
            'name',
            'contact_name',
            'phone',
            'email',
            'address',
            'is_active',
            'notes',
        ]);

        $supplier->fill($payload);
        $supplier->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.suppliers.update',
            auditable: $supplier,
            oldValues: $oldValues,
            newValues: $supplier->only([
                'name',
                'contact_name',
                'phone',
                'email',
                'address',
                'is_active',
                'notes',
            ]),
        );

        return response()->json([
            'data' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'is_active' => (bool) $supplier->is_active,
                'notes' => $supplier->notes,
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
