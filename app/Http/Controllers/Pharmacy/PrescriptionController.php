<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Actions\Pharmacy\DispensePrescriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\DispensePrescriptionRequest;
use App\Http\Requests\Pharmacy\StorePrescriptionRequest;
use App\Models\DrugBatch;
use App\Models\PharmacyStockMovement;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionController extends Controller
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private DispensePrescriptionAction $dispensePrescriptionAction,
    ) {}

    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $status = $request->get('status');
        $doctorId = $request->get('doctor_id');

        $query = Prescription::query()
            ->forClinic($clinicId)
            ->with(['patient:id,clinic_id,first_name,last_name,full_name,file_number', 'prescriber:id,clinic_id,name', 'items'])
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('prescription_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($pq) use ($search): void {
                        $pq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', Prescription::PHARMACY_STATUSES);
        }

        if ($doctorId) {
            $query->where('prescribed_by', (int) $doctorId);
        }

        $prescriptions = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json(['data' => $prescriptions]);
        }

        return Inertia::render('pharmacy/Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'status' => $status,
                'doctor_id' => $doctorId,
            ],
        ]);
    }

    public function show(Request $request, int $prescriptionId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name,full_name,file_number,phone,date_of_birth,gender',
                'prescriber:id,clinic_id,name',
                'prescriber.doctorProfile:id,clinic_id,user_id,specialty,license_number',
                'items.drug:id,clinic_id,trade_name,generic_name,current_stock,min_stock_level,form,unit,strength',
            ])
            ->whereKey($prescriptionId)
            ->firstOrFail();

        $itemsWithStock = $prescription->items->map(function (PrescriptionItem $item) use ($clinicId): array {
            $drug = $item->drug;
            $availableBatches = [];

            if ($drug) {
                $batches = DrugBatch::query()
                    ->forClinic($clinicId)
                    ->where('pharmacy_drug_id', $drug->id)
                    ->where('quantity', '>', 0)
                    ->notExpired()
                    ->orderBy('expiry_date')
                    ->get();

                $availableBatches = $batches->map(fn (DrugBatch $batch): array => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => (int) $batch->quantity,
                    'expiry_date' => $batch->expiry_date?->toDateString(),
                    'remaining_days' => $batch->remainingDays(),
                ])->values()->all();
            }

            return [
                'id' => $item->id,
                'medication_name' => $item->medication_name,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'quantity' => (int) $item->quantity,
                'quantity_dispensed' => (int) $item->quantity_dispensed,
                'remaining_quantity' => $item->remainingQuantity(),
                'instructions' => $item->instructions,
                'status' => $item->status,
                'substitution_allowed' => (bool) $item->substitution_allowed,
                'drug' => $drug ? [
                    'id' => $drug->id,
                    'trade_name' => $drug->trade_name,
                    'generic_name' => $drug->generic_name,
                    'current_stock' => (int) $drug->current_stock,
                    'form' => $drug->form,
                    'unit' => $drug->unit,
                    'strength' => $drug->strength,
                ] : null,
                'available_batches' => $availableBatches,
            ];
        });

        return response()->json([
            'data' => [
                'id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'status' => $prescription->status,
                'issued_at' => $prescription->issued_at?->toISOString(),
                'sent_to_pharmacy_at' => $prescription->sent_to_pharmacy_at?->toISOString(),
                'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                'diagnosis' => $prescription->diagnosis,
                'notes' => $prescription->notes,
                'patient' => $prescription->patient,
                'prescriber' => [
                    'name' => $prescription->prescriber?->name,
                    'specialty' => $prescription->prescriber?->doctorProfile?->specialty,
                    'license_number' => $prescription->prescriber?->doctorProfile?->license_number,
                ],
                'items' => $itemsWithStock,
            ],
        ]);
    }

    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $payload = $request->validated();

        $prescription = DB::transaction(function () use ($clinicId, $request, $payload): Prescription {
            $prescription = Prescription::query()->create([
                'clinic_id' => $clinicId,
                'visit_id' => null,
                'patient_id' => (int) $payload['patient_id'],
                'prescribed_by' => $request->user()?->id,
                'prescription_number' => $payload['prescription_number'] ?? $this->generatePrescriptionNumber($clinicId),
                'status' => Prescription::STATUS_SENT_TO_PHARMACY,
                'issued_at' => now(),
                'sent_to_pharmacy_at' => now(),
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($payload['items'] as $item) {
                PrescriptionItem::query()->create([
                    'clinic_id' => $clinicId,
                    'prescription_id' => $prescription->id,
                    'pharmacy_drug_id' => $item['pharmacy_drug_id'] ?? null,
                    'medication_name' => $item['medication_name'],
                    'dosage' => $item['dosage'],
                    'frequency' => $item['frequency'],
                    'duration' => $item['duration'] ?? null,
                    'quantity' => (int) $item['quantity'],
                    'instructions' => $item['instructions'] ?? null,
                    'status' => PrescriptionItem::STATUS_PENDING,
                ]);
            }

            return $prescription->load('items');
        });

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.prescriptions.create',
            auditable: $prescription,
            newValues: [
                'prescription_number' => $prescription->prescription_number,
                'status' => $prescription->status,
                'items_count' => $prescription->items->count(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'status' => $prescription->status,
                'items_count' => $prescription->items->count(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, int $prescriptionId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:received,preparing,ready,canceled'],
        ]);

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->whereKey($prescriptionId)
            ->firstOrFail();

        $newStatus = $validated['status'];
        $statusMap = [
            'received' => Prescription::STATUS_RECEIVED,
            'preparing' => Prescription::STATUS_PREPARING,
            'ready' => Prescription::STATUS_READY,
            'canceled' => Prescription::STATUS_CANCELED,
        ];

        $targetStatus = $statusMap[$newStatus] ?? $newStatus;
        $success = $prescription->transitionTo($targetStatus);

        if (! $success) {
            return response()->json([
                'message' => "لا يمكن تغيير الحالة من '{$prescription->status}' إلى '{$targetStatus}'.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'pharmacy.prescriptions.status_change',
            auditable: $prescription,
            metadata: ['new_status' => $targetStatus],
        );

        return response()->json([
            'data' => [
                'id' => $prescription->id,
                'status' => $prescription->status,
            ],
        ]);
    }

    public function dispense(DispensePrescriptionRequest $request, int $prescriptionId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $userId = (int) $request->user()->id;

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->whereKey($prescriptionId)
            ->firstOrFail();

        $dispense = $this->dispensePrescriptionAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            prescription: $prescription,
            itemsData: $request->validated('items'),
            notes: $request->validated('notes'),
        );

        return response()->json([
            'data' => [
                'id' => $dispense->id,
                'prescription_id' => $dispense->prescription_id,
                'total_amount' => (float) $dispense->total_amount,
                'dispensed_at' => $dispense->dispensed_at?->toISOString(),
                'items_count' => $dispense->items->count(),
            ],
        ]);
    }

    public function stockMovements(Request $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 25);
        $drugId = $request->get('drug_id');
        $movementType = $request->get('movement_type');

        $query = PharmacyStockMovement::query()
            ->forClinic($clinicId)
            ->with(['drug:id,clinic_id,trade_name,generic_name', 'batch:id,batch_number', 'creator:id,name'])
            ->orderByDesc('created_at');

        if ($drugId) {
            $query->where('pharmacy_drug_id', (int) $drugId);
        }

        if ($movementType) {
            $query->where('movement_type', $movementType);
        }

        $movements = $query->paginate($perPage);

        return response()->json([
            'data' => $movements->map(fn (PharmacyStockMovement $m): array => [
                'id' => $m->id,
                'drug_name' => $m->drug?->trade_name ?? '-',
                'batch_number' => $m->batch?->batch_number,
                'movement_type' => $m->movement_type,
                'quantity' => (int) $m->quantity,
                'previous_quantity' => (int) $m->previous_quantity,
                'new_quantity' => (int) $m->new_quantity,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'notes' => $m->notes,
                'created_by' => $m->creator?->name,
                'created_at' => $m->created_at?->toISOString(),
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

    private function generatePrescriptionNumber(int $clinicId): string
    {
        $today = now()->toDateString();
        $sequence = (int) Prescription::query()
            ->forClinic($clinicId)
            ->whereDate('created_at', $today)
            ->count() + 1;

        return sprintf('RX-%s-%04d', now()->format('Ymd'), $sequence);
    }
}
