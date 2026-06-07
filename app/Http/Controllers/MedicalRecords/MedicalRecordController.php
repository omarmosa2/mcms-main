<?php

namespace App\Http\Controllers\MedicalRecords;

use App\Actions\MedicalRecords\ListMedicalRecordsAction;
use App\Actions\MedicalRecords\StoreMedicalRecordAction;
use App\Actions\MedicalRecords\UpdateMedicalRecordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalRecords\StoreMedicalRecordRequest;
use App\Http\Requests\MedicalRecords\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\Department;
use App\Models\MedicalRecord;
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
        $filters = $this->resolveFilters($request);

        $records = $this->listMedicalRecordsAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            search: $filters['search'],
            departmentId: $filters['department_id'],
            doctorId: $filters['doctor_id'],
            clinicType: $filters['clinic_type'],
            status: $filters['status'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
            diagnosis: $filters['diagnosis'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
        );

        $departments = Department::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->get(['id', 'name', 'clinic_type']);

        $doctors = User::query()
            ->forClinic($clinicId)
            ->whereHas('doctorProfile')
            ->get(['id', 'name']);

        $recordsResource = MedicalRecordResource::collection($records);

        if ($request->expectsJson()) {
            return $recordsResource;
        }

        return Inertia::render('medical-records/Index', [
            'records' => $recordsResource->response()->getData(true),
            'departments' => $departments,
            'doctors' => $doctors,
            'clinicTypes' => MedicalRecord::CLINIC_TYPES,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $departments = Department::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->get(['id', 'name', 'clinic_type']);

        return Inertia::render('medical-records/Create', [
            'departments' => $departments,
            'clinicTypes' => MedicalRecord::CLINIC_TYPES,
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
                'department:id,clinic_id,name,clinic_type',
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
                'department:id,clinic_id,name,clinic_type',
                'doctor:id,clinic_id,name',
                'creator:id,clinic_id,name',
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
                'department:id,clinic_id,name,clinic_type',
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
     *     department_id: ?int,
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
    private function resolveFilters(Request $request): array
    {
        return [
            'search' => $request->filled('search') ? trim($request->query('search', '')) : null,
            'per_page' => $this->normalizePerPage($request->query('per_page', 15)),
            'department_id' => $request->filled('department_id') ? (int) $request->query('department_id') : null,
            'doctor_id' => $request->filled('doctor_id') ? (int) $request->query('doctor_id') : null,
            'clinic_type' => $request->filled('clinic_type') ? $request->query('clinic_type') : null,
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
