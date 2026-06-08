<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DoctorPrescriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $query = Prescription::query()
            ->forClinic($clinicId)
            ->where('prescribed_by', $doctorId)
            ->with(['patient:id,clinic_id,first_name,last_name,file_number', 'items', 'medicalRecord:id,clinic_id,primary_diagnosis'])
            ->orderByDesc('created_at');

        $search = $request->get('search');
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('prescription_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($pq) use ($search): void {
                        $pq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $status = $request->get('status');
        if ($status) {
            $query->where('status', $status);
        }

        $prescriptions = $query->paginate(15);

        return Inertia::render('doctor/Prescriptions', [
            'prescriptions' => $prescriptions,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;

        $patients = Patient::query()
            ->forClinic($clinicId)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'file_number']);

        $medicalRecordId = $request->query('medical_record_id');
        $medicalRecord = null;

        if ($medicalRecordId) {
            $medicalRecord = MedicalRecord::query()
                ->forClinic($clinicId)
                ->whereKey($medicalRecordId)
                ->first(['id', 'patient_id', 'primary_diagnosis']);
        }

        return Inertia::render('doctor/PrescriptionCreate', [
            'patients' => $patients,
            'medical_record' => $medicalRecord,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'medical_record_id' => ['nullable', 'integer'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.dosage' => ['required', 'string', 'max:255'],
            'items.*.frequency' => ['required', 'string', 'max:255'],
            'items.*.duration' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $prescriptionNumber = $this->generatePrescriptionNumber($clinicId);

        $prescription = DB::transaction(function () use ($clinicId, $doctorId, $validated, $prescriptionNumber) {
            $prescription = Prescription::query()->create([
                'clinic_id' => $clinicId,
                'patient_id' => (int) $validated['patient_id'],
                'prescribed_by' => $doctorId,
                'visit_id' => null,
                'medical_record_id' => $validated['medical_record_id'] ?? null,
                'prescription_number' => $prescriptionNumber,
                'status' => Prescription::STATUS_ISSUED,
                'issued_at' => now(),
                'diagnosis' => $validated['diagnosis'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                PrescriptionItem::query()->create([
                    'clinic_id' => $clinicId,
                    'prescription_id' => $prescription->id,
                    'medication_name' => $item['medication_name'],
                    'dosage' => $item['dosage'],
                    'frequency' => $item['frequency'],
                    'duration' => $item['duration'] ?? null,
                    'quantity' => (int) $item['quantity'],
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            return $prescription->load(['items', 'patient:id,clinic_id,first_name,last_name,file_number']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إنشاء الوصفة الطبية بنجاح.']);

        return to_route('doctor.prescriptions.show', $prescription->id);
    }

    public function show(Request $request, int $prescriptionId): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->where('prescribed_by', $doctorId)
            ->whereKey($prescriptionId)
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth,gender',
                'prescriber:id,clinic_id,name',
                'prescriber.doctorProfile:id,clinic_id,user_id,specialty,license_number',
                'items',
                'medicalRecord:id,clinic_id,primary_diagnosis',
            ])
            ->firstOrFail();

        $clinic = $user->clinic;
        $branding = BrandingSetting::query()->forClinic($clinicId)->first();

        return Inertia::render('doctor/PrescriptionShow', [
            'prescription' => [
                'id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'status' => $prescription->status,
                'issued_at' => $prescription->issued_at?->toISOString(),
                'diagnosis' => $prescription->diagnosis,
                'notes' => $prescription->notes,
                'patient' => $prescription->patient,
                'prescriber' => [
                    'name' => $prescription->prescriber?->name,
                    'specialty' => $prescription->prescriber?->doctorProfile?->specialty,
                    'license_number' => $prescription->prescriber?->doctorProfile?->license_number,
                ],
                'items' => $prescription->items,
                'medical_record' => $prescription->medicalRecord,
            ],
            'clinic' => [
                'name' => $clinic?->name,
                'logo_path' => $branding?->logo_path,
            ],
        ]);
    }

    public function edit(Request $request, int $prescriptionId): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->where('prescribed_by', $doctorId)
            ->whereKey($prescriptionId)
            ->with(['items'])
            ->firstOrFail();

        $patients = Patient::query()
            ->forClinic($clinicId)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'file_number']);

        return Inertia::render('doctor/PrescriptionEdit', [
            'prescription' => [
                'id' => $prescription->id,
                'prescription_number' => $prescription->prescription_number,
                'patient_id' => $prescription->patient_id,
                'diagnosis' => $prescription->diagnosis,
                'notes' => $prescription->notes,
                'items' => $prescription->items->map(fn ($item) => [
                    'id' => $item->id,
                    'medication_name' => $item->medication_name,
                    'dosage' => $item->dosage,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'quantity' => $item->quantity,
                    'instructions' => $item->instructions,
                ]),
            ],
            'patients' => $patients,
        ]);
    }

    public function update(Request $request, int $prescriptionId)
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->where('prescribed_by', $doctorId)
            ->whereKey($prescriptionId)
            ->firstOrFail();

        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.dosage' => ['required', 'string', 'max:255'],
            'items.*.frequency' => ['required', 'string', 'max:255'],
            'items.*.duration' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($prescription, $clinicId, $validated): void {
            $prescription->update([
                'patient_id' => (int) $validated['patient_id'],
                'diagnosis' => $validated['diagnosis'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $existingItemIds = collect($validated['items'])->pluck('id')->filter()->all();

            PrescriptionItem::query()
                ->forClinic($clinicId)
                ->where('prescription_id', $prescription->id)
                ->whereNotIn('id', $existingItemIds)
                ->delete();

            foreach ($validated['items'] as $item) {
                PrescriptionItem::query()->updateOrCreate(
                    ['id' => $item['id'] ?? null, 'clinic_id' => $clinicId, 'prescription_id' => $prescription->id],
                    [
                        'medication_name' => $item['medication_name'],
                        'dosage' => $item['dosage'],
                        'frequency' => $item['frequency'],
                        'duration' => $item['duration'] ?? null,
                        'quantity' => (int) $item['quantity'],
                        'instructions' => $item['instructions'] ?? null,
                    ],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث الوصفة الطبية بنجاح.']);

        return to_route('doctor.prescriptions.show', $prescription->id);
    }

    public function print(Request $request, int $prescriptionId)
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->where('prescribed_by', $doctorId)
            ->whereKey($prescriptionId)
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth,gender',
                'prescriber:id,clinic_id,name',
                'prescriber.doctorProfile:id,clinic_id,user_id,specialty,license_number',
                'items',
            ])
            ->firstOrFail();

        $clinic = $user->clinic;
        $branding = BrandingSetting::query()->forClinic($clinicId)->first();

        return view('doctor.prescriptions.print', [
            'prescription' => $prescription,
            'clinic' => $clinic,
            'branding' => $branding,
        ]);
    }

    public function pdf(Request $request, int $prescriptionId)
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $prescription = Prescription::query()
            ->forClinic($clinicId)
            ->where('prescribed_by', $doctorId)
            ->whereKey($prescriptionId)
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth,gender',
                'prescriber:id,clinic_id,name',
                'prescriber.doctorProfile:id,clinic_id,user_id,specialty,license_number',
                'items',
            ])
            ->firstOrFail();

        $clinic = $user->clinic;
        $branding = BrandingSetting::query()->forClinic($clinicId)->first();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('doctor.prescriptions.print', [
            'prescription' => $prescription,
            'clinic' => $clinic,
            'branding' => $branding,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("وصفة-{$prescription->prescription_number}.pdf");
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
