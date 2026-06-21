<?php

namespace App\Http\Controllers\MedicalRecords;

use App\Actions\MedicalRecords\ListMedicalRecordsAction;
use App\Actions\MedicalRecords\StoreMedicalRecordAction;
use App\Actions\MedicalRecords\UpdateMedicalRecordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalRecords\StoreMedicalRecordRequest;
use App\Http\Requests\MedicalRecords\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MedicalRecordController extends Controller
{
    public function __construct(
        private ListMedicalRecordsAction $listMedicalRecordsAction,
        private StoreMedicalRecordAction $storeMedicalRecordAction,
        private UpdateMedicalRecordAction $updateMedicalRecordAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $user = $request->user();
        $isDoctor = $user !== null && $user->hasRole('doctor');
        $filters = $this->resolveFilters($request, $isDoctor);

        $records = $this->listMedicalRecordsAction->handle(
            clinicId: $clinicId,
            userId: (int) $user->id,
            perPage: $filters['per_page'],
            search: $filters['search'],
            clinicFilterId: $isDoctor ? null : $filters['clinic_id'],
            doctorId: $filters['doctor_id'],
            clinicType: $isDoctor ? null : $filters['clinic_type'],
            status: $filters['status'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
            diagnosis: $filters['diagnosis'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $clinics = $isDoctor
            ? collect()
            : Clinic::query()
                ->whereKey($clinicId)
                ->where('is_active', true)
                ->get(['id', 'name', 'code']);

        $doctors = $isDoctor
            ? collect()
            : User::query()
                ->forClinic($clinicId)
                ->whereHas('doctorProfile')
                ->get(['id', 'name']);

        $recordsResource = MedicalRecordResource::collection($records);

        if ($request->expectsJson()) {
            return $recordsResource;
        }

        return Inertia::render('medical-records/Index', [
            'records' => $recordsResource->response()->getData(true),
            'clinics' => $clinics,
            'doctors' => $doctors,
            'clinicTypes' => $isDoctor ? [] : MedicalRecord::CLINIC_TYPES,
            'filters' => $filters,
            'is_doctor' => $isDoctor,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $clinics = Clinic::query()
            ->whereKey($clinicId)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        $patients = Patient::query()
            ->forClinic($clinicId)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'file_number']);

        $appointmentData = null;
        $patientId = $request->query('patient_id');

        if ($patientId) {
            $appointment = Appointment::query()
                ->forClinic($clinicId)
                ->where('patient_id', (int) $patientId)
                ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
                ->orderByDesc('scheduled_for')
                ->first();

            if ($appointment) {
                $appointmentData = [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'clinic_id' => $appointment->clinic_id,
                    'scheduled_for' => $appointment->scheduled_for,
                    'appointment_type' => $appointment->appointment_type,
                ];
            }
        }

        return Inertia::render('medical-records/Create', [
            'clinics' => $clinics,
            'clinicTypes' => MedicalRecord::CLINIC_TYPES,
            'patients' => $patients,
            'appointment_data' => $appointmentData,
        ]);
    }

    public function store(StoreMedicalRecordRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $record = $this->storeMedicalRecordAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return MedicalRecordResource::make($record->load([
                'patient:id,clinic_id,first_name,last_name,file_number',
                'clinic:id,name,code',
                'doctor:id,clinic_id,name',
                'treatmentPlans',
                'followUps',
            ]))
                ->response()
                ->setStatusCode(SymfonyResponse::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إنشاء السجل الطبي بنجاح.']);

        return to_route('medical-records.index');
    }

    public function show(Request $request, int $recordId): MedicalRecordResource|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $record = MedicalRecord::query()
            ->forClinic($clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth,gender',
                'patient.allergies:id,clinic_id,patient_id,allergy,created_at',
                'patient.attachments:id,clinic_id,patient_id,uploaded_by,original_name,mime_type,extension,size_bytes,uploaded_at,created_at',
                'patient.attachments.uploader:id,clinic_id,name,email',
                'patient.chronicConditions:id,clinic_id,patient_id,condition,created_at',
                'patient.labOrders:id,clinic_id,visit_id,patient_id,ordered_by,test_code,test_name,status,ordered_at,notes',
                'patient.labOrders.orderer:id,clinic_id,name',
                'patient.labOrders.results:id,clinic_id,lab_order_id,resulted_by,result_value,reference_range,unit,resulted_at,notes',
                'patient.medications:id,clinic_id,patient_id,medication,created_at',
                'patient.radiologyOrders:id,clinic_id,visit_id,patient_id,ordered_by,study_code,study_name,modality,status,ordered_at,notes',
                'patient.radiologyOrders.orderer:id,clinic_id,name',
                'patient.radiologyOrders.reports:id,clinic_id,radiology_order_id,reported_by,report_text,reported_at',
                'clinic:id,name,code',
                'doctor:id,clinic_id,name',
                'creator:id,clinic_id,name',
                'auditLogs' => fn ($query) => $query
                    ->with('user:id,clinic_id,name')
                    ->latest('occurred_at')
                    ->limit(20),
                'prescriptions:id,clinic_id,visit_id,medical_record_id,patient_id,prescribed_by,prescription_number,status,issued_at,dispensed_at,notes,diagnosis,created_at',
                'prescriptions.items:id,clinic_id,prescription_id,medication_name,dosage,frequency,duration,quantity,instructions',
                'prescriptions.prescriber:id,clinic_id,name',
                'treatmentPlans',
                'treatmentPlans.doctor:id,clinic_id,name',
                'followUps',
                'followUps.doctor:id,clinic_id,name',
            ])
            ->whereKey($recordId)
            ->firstOrFail();

        if ($request->expectsJson()) {
            return MedicalRecordResource::make($record);
        }

        return Inertia::render('medical-records/Show', [
            'record' => MedicalRecordResource::make($record)->response()->getData(true),
        ]);
    }

    public function update(UpdateMedicalRecordRequest $request, int $recordId): MedicalRecordResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $record = $this->updateMedicalRecordAction->handle(
            clinicId: $clinicId,
            medicalRecordId: $recordId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return MedicalRecordResource::make($record->load([
                'patient:id,clinic_id,first_name,last_name,file_number',
                'clinic:id,name,code',
                'doctor:id,clinic_id,name',
                'treatmentPlans',
                'followUps',
            ]));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث السجل الطبي بنجاح.']);

        return to_route('medical-records.show', $recordId);
    }

    public function destroy(Request $request, int $recordId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $record = MedicalRecord::query()
            ->forClinic($clinicId)
            ->whereKey($recordId)
            ->firstOrFail();

        $record->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف السجل الطبي بنجاح.']);

        return to_route('medical-records.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array{
     *     search: ?string,
     *     per_page: int,
     *     clinic_id: ?int,
     *     doctor_id: ?int,
     *     clinic_type: ?string,
     *     status: ?string,
     *     date_from: ?string,
     *     date_to: ?string,
     *     diagnosis: ?string,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveFilters(Request $request, bool $isDoctor = false): array
    {
        return [
            'search' => $request->filled('search') ? trim($request->query('search', '')) : null,
            'per_page' => $this->normalizePerPage($request->query('per_page', 15)),
            'clinic_id' => ($isDoctor || ! $request->filled('clinic_id')) ? null : (int) $request->query('clinic_id'),
            'doctor_id' => $request->filled('doctor_id') ? (int) $request->query('doctor_id') : null,
            'clinic_type' => ($isDoctor || ! $request->filled('clinic_type')) ? null : $request->query('clinic_type'),
            'status' => $request->filled('status') ? $request->query('status') : null,
            'date_from' => $request->filled('date_from') ? $request->query('date_from') : null,
            'date_to' => $request->filled('date_to') ? $request->query('date_to') : null,
            'diagnosis' => $request->filled('diagnosis') ? trim($request->query('diagnosis', '')) : null,
            'sort_by' => $this->normalizeSortBy($request->query('sort_by', 'created_at')),
            'sort_direction' => $this->normalizeSortDirection($request->query('sort_direction', 'desc')),
        ];
    }

    private function normalizePerPage(mixed $value): int
    {
        $perPage = (int) $value;
        $allowed = [10, 15, 25, 50];

        return in_array($perPage, $allowed, true) ? $perPage : 15;
    }

    private function normalizeSortBy(mixed $value): string
    {
        $sortBy = trim((string) ($value ?? ''));
        $allowed = ['visit_date', 'patient_name', 'status', 'created_at'];

        return in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
