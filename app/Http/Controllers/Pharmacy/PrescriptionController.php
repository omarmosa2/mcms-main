<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\DispensePrescriptionRequest;
use App\Http\Requests\Pharmacy\StorePrescriptionRequest;
use App\Models\PharmacyDispense;
use App\Models\PharmacyDrug;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function index(Request $request): JsonResponse|\Inertia\Response
    {
        $clinicId = $this->resolveClinicId($request);
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');
        $status = $request->get('status');

        $query = Prescription::query()
            ->forClinic($clinicId)
            ->with(['patient:id,clinic_id,full_name', 'visit:id,clinic_id,visit_number', 'prescriber:id,clinic_id,name', 'items'])
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('prescription_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($pq) use ($search): void {
                        $pq->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
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
                'status' => Prescription::STATUS_ISSUED,
                'issued_at' => now(),
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
                'visit_id' => $prescription->visit_id,
                'patient_id' => $prescription->patient_id,
                'prescription_number' => $prescription->prescription_number,
                'status' => $prescription->status,
                'issued_at' => $prescription->issued_at?->toISOString(),
                'items_count' => $prescription->items->count(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function dispense(DispensePrescriptionRequest $request, int $prescriptionId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $userId = (int) $request->user()->id;

        $dispense = DB::transaction(function () use ($clinicId, $request, $prescriptionId, $userId): PharmacyDispense {
            $prescription = Prescription::query()
                ->forClinic($clinicId)
                ->with(['items', 'items.drug'])
                ->whereKey($prescriptionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($prescription->status === Prescription::STATUS_DISPENSED) {
                throw ValidationException::withMessages([
                    'prescription' => 'Prescription has already been dispensed.',
                ]);
            }

            if ($prescription->status === Prescription::STATUS_CANCELED) {
                throw ValidationException::withMessages([
                    'prescription' => 'Canceled prescriptions cannot be dispensed.',
                ]);
            }

            if ($prescription->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Prescription contains no items to dispense.',
                ]);
            }

            $totalAmount = 0.0;
            $dispense = PharmacyDispense::query()->create([
                'clinic_id' => $clinicId,
                'prescription_id' => $prescription->id,
                'dispensed_by' => $userId,
                'dispensed_at' => now(),
                'total_amount' => 0,
                'notes' => $request->validated()['notes'] ?? null,
            ]);

            foreach ($prescription->items as $item) {
                if ($item->pharmacy_drug_id === null) {
                    throw ValidationException::withMessages([
                        'items' => 'All prescription items must be linked to a pharmacy drug before dispensing.',
                    ]);
                }

                $drug = PharmacyDrug::query()
                    ->forClinic($clinicId)
                    ->whereKey($item->pharmacy_drug_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $drug->current_stock < (int) $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => sprintf('Insufficient stock for %s.', $drug->trade_name),
                    ]);
                }

                $lineTotal = round((float) $drug->unit_price * (int) $item->quantity, 2);
                $totalAmount += $lineTotal;

                $drug->current_stock = (int) $drug->current_stock - (int) $item->quantity;
                $drug->save();

                $dispense->items()->create([
                    'clinic_id' => $clinicId,
                    'prescription_item_id' => $item->id,
                    'pharmacy_drug_id' => $drug->id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $drug->unit_price,
                    'line_total' => $lineTotal,
                ]);
            }

            $dispense->total_amount = round($totalAmount, 2);
            $dispense->save();

            $prescription->status = Prescription::STATUS_DISPENSED;
            $prescription->dispensed_at = now();
            $prescription->save();

            return $dispense->load(['items', 'prescription']);
        });

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'pharmacy.prescriptions.dispense',
            auditable: $dispense->prescription,
            metadata: [
                'dispense_id' => $dispense->id,
                'items_count' => $dispense->items->count(),
                'total_amount' => (float) $dispense->total_amount,
            ],
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
