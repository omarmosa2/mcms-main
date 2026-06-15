<?php

namespace App\Http\Controllers\Appointments;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\DeleteAppointmentAction;
use App\Actions\Appointments\ListAppointmentsAction;
use App\Actions\Appointments\ShowAppointmentAction;
use App\Actions\Appointments\TransitionAppointmentStatusAction;
use App\Actions\Appointments\UpdateAppointmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\TransitionAppointmentStatusRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\PatientCardVisit;
use App\Services\Cache\CacheService;
use App\Services\ClinicWorkingHoursService;
use App\Services\DoctorAvailabilityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private ListAppointmentsAction $listAppointmentsAction,
        private ShowAppointmentAction $showAppointmentAction,
        private CreateAppointmentAction $createAppointmentAction,
        private UpdateAppointmentAction $updateAppointmentAction,
        private TransitionAppointmentStatusAction $transitionAppointmentStatusAction,
        private DeleteAppointmentAction $deleteAppointmentAction,
        private CacheService $cacheService,
        private ClinicWorkingHoursService $clinicWorkingHoursService,
        private DoctorAvailabilityService $doctorAvailabilityService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);
        $doctorScopeUserId = $this->resolveDoctorScopeUserId($request);

        $appointments = $this->listAppointmentsAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            perPage: $filters['per_page'],
            status: $filters['status'],
            search: $filters['search'],
            sortBy: $filters['sort_by'],
            sortDirection: $filters['sort_direction'],
            doctorId: $doctorScopeUserId ?? $filters['doctor_id'],
            departmentId: $filters['department_id'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
        );

        $appointmentsResource = AppointmentResource::collection($appointments);

        if ($request->expectsJson()) {
            return $appointmentsResource;
        }

        $patients = $this->cacheService->getPatientsDropdown($clinicId);

        $doctors = $this->cacheService->getDoctorsDropdown($clinicId);
        $departments = $this->cacheService
            ->getClinicDepartments($clinicId)
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->values()
            ->all();

        $todayAppointments = Appointment::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth',
                'doctor:id,clinic_id,name',
                'doctor.doctorProfile:id,clinic_id,user_id,department_id,specialty,status',
                'doctor.doctorProfile.department:id,clinic_id,name',
            ])
            ->whereDate('scheduled_for', now()->toDateString())
            ->orderBy('scheduled_for')
            ->get();

        return Inertia::render('appointments/Index', [
            'appointments' => $appointmentsResource->response()->getData(true),
            'patients' => $patients,
            'doctors' => $doctors,
            'departments' => $departments,
            'status_options' => [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_ARRIVED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELED,
                Appointment::STATUS_NO_SHOW,
            ],
            'filters' => $filters,
            'clinic_working_hours' => $this->clinicWorkingHoursService->getForClinic($clinicId),
            'today_availability' => $this->resolveTodayAvailability($clinicId, $doctors),
            'today_appointments' => AppointmentResource::collection($todayAppointments)->response()->getData(true)['data'],
            'is_doctor' => $doctorScopeUserId !== null,
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $appointment = $this->createAppointmentAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return AppointmentResource::make($appointment)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment created successfully.']);

        return to_route('appointments.index');
    }

    public function show(Request $request, int $appointmentId): AppointmentResource
    {
        $clinicId = $this->resolveClinicId($request);

        $appointment = $this->showAppointmentAction->handle(
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            userId: (int) $request->user()->id,
            doctorId: $this->resolveDoctorScopeUserId($request),
        );

        return AppointmentResource::make($appointment);
    }

    public function update(UpdateAppointmentRequest $request, int $appointmentId): AppointmentResource|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $appointment = $this->updateAppointmentAction->handle(
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return AppointmentResource::make($appointment);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment updated successfully.']);

        return to_route('appointments.index');
    }

    public function transitionStatus(
        TransitionAppointmentStatusRequest $request,
        int $appointmentId,
    ): AppointmentResource|RedirectResponse {
        $clinicId = $this->resolveClinicId($request);

        $appointment = $this->transitionAppointmentStatusAction->handle(
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return AppointmentResource::make($appointment);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment status updated successfully.']);

        return to_route('appointments.index');
    }

    public function destroy(Request $request, int $appointmentId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteAppointmentAction->handle(
            clinicId: $clinicId,
            appointmentId: $appointmentId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment deleted successfully.']);

        return to_route('appointments.index');
    }

    public function startVisit(Request $request, int $appointmentId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $userId = (int) $request->user()->id;

        $appointment = Appointment::query()
            ->forClinic($clinicId)
            ->with(['patient:id,clinic_id', 'doctor:id,clinic_id,name', 'doctor.doctorProfile:id,clinic_id,user_id,department_id'])
            ->whereKey($appointmentId)
            ->firstOrFail();

        $existingVisit = PatientCardVisit::query()
            ->forClinic($clinicId)
            ->where('appointment_id', $appointment->id)
            ->first();

        if ($existingVisit !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'تم فتح الزيارة الطبية المرتبطة بهذا الموعد.']);

            return to_route('patients.card.show', $appointment->patient_id, ['appointment_id' => $appointment->id]);
        }

        $doctorId = $appointment->doctor_id;
        $departmentId = $appointment->doctor?->doctorProfile?->department_id;
        $scheduledFor = $appointment->scheduled_for;

        PatientCardVisit::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $doctorId,
            'department_id' => $departmentId,
            'visit_date' => $scheduledFor->toDateString(),
            'visit_time' => $scheduledFor->format('H:i'),
            'created_by' => $userId,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إنشاء الزيارة الطبية من الموعد.']);

        return to_route('patients.card.show', $appointment->patient_id, ['appointment_id' => $appointment->id]);
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $clinicId = $this->resolveClinicId($request);
        $userId = (int) $request->user()->id;

        $deletedIds = [];
        $failedIds = [];

        /** @var array<int> $ids */
        $ids = array_map('intval', $validated['ids']);

        DB::transaction(function () use ($ids, $clinicId, $userId, &$deletedIds, &$failedIds): void {
            foreach (array_values(array_unique($ids)) as $appointmentId) {
                try {
                    $this->deleteAppointmentAction->handle(
                        clinicId: $clinicId,
                        appointmentId: $appointmentId,
                        userId: $userId,
                    );

                    $deletedIds[] = $appointmentId;
                } catch (ModelNotFoundException|ValidationException) {
                    $failedIds[] = $appointmentId;
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'deleted_ids' => $deletedIds,
                    'failed_ids' => $failedIds,
                    'deleted_count' => count($deletedIds),
                    'failed_count' => count($failedIds),
                ],
            ], count($deletedIds) > 0 ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($deletedIds) === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No selected appointments could be deleted.']);

            return to_route('appointments.index');
        }

        if (count($failedIds) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => sprintf('Deleted %d appointment(s). %d could not be deleted.', count($deletedIds), count($failedIds)),
            ]);

            return to_route('appointments.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Deleted %d appointment(s) successfully.', count($deletedIds)),
        ]);

        return to_route('appointments.index');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @param  array<int, array{id: int, department_id?: int|null}>  $doctors
     * @return array{
     *     date: string,
     *     departments: array<int, int>,
     *     doctors: array<int, array{
     *         id: int,
     *         department_id: int,
     *         available_periods: array<int, array{start_time: string, end_time: string}>
     *     }>,
     *     department_periods: array<int, array<int, array{start_time: string, end_time: string}>>
     * }
     */
    private function resolveTodayAvailability(int $clinicId, array $doctors): array
    {
        $today = now()->toDateString();
        $availableDoctors = [];
        $departmentPeriods = [];

        foreach ($doctors as $doctor) {
            $doctorId = (int) ($doctor['id'] ?? 0);
            $departmentId = (int) ($doctor['department_id'] ?? 0);

            if ($doctorId <= 0 || $departmentId <= 0) {
                continue;
            }

            $availability = $this->doctorAvailabilityService->availabilityForDay(
                clinicId: $clinicId,
                doctorId: $doctorId,
                date: $today,
                departmentId: $departmentId,
            );

            if (! $availability['is_available']) {
                continue;
            }

            $availableDoctors[] = [
                'id' => $doctorId,
                'department_id' => $departmentId,
                'available_periods' => $availability['available_periods'],
            ];

            $departmentPeriods[$departmentId] = $this->mergePeriods([
                ...($departmentPeriods[$departmentId] ?? []),
                ...$availability['available_periods'],
            ]);
        }

        return [
            'date' => $today,
            'departments' => array_values(array_unique(array_column($availableDoctors, 'department_id'))),
            'doctors' => $availableDoctors,
            'department_periods' => $departmentPeriods,
        ];
    }

    /**
     * @param  array<int, array{start_time: string, end_time: string}>  $periods
     * @return array<int, array{start_time: string, end_time: string}>
     */
    private function mergePeriods(array $periods): array
    {
        usort($periods, fn (array $first, array $second): int => $first['start_time'] <=> $second['start_time']);

        $merged = [];

        foreach ($periods as $period) {
            $lastIndex = count($merged) - 1;

            if ($lastIndex < 0 || $period['start_time'] > $merged[$lastIndex]['end_time']) {
                $merged[] = $period;

                continue;
            }

            if ($period['end_time'] > $merged[$lastIndex]['end_time']) {
                $merged[$lastIndex]['end_time'] = $period['end_time'];
            }
        }

        return $merged;
    }

    private function resolveDoctorScopeUserId(Request $request): ?int
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole('doctor')) {
            return (int) $user->id;
        }

        return null;
    }

    /**
     * @return array{
     *     status: ?string,
     *     search: ?string,
     *     doctor_id: ?int,
     *     department_id: ?int,
     *     date_from: ?string,
     *     date_to: ?string,
     *     per_page: int,
     *     sort_by: string,
     *     sort_direction: string
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $sessionKey = 'appointments.index.filters';

        if ($request->boolean('reset')) {
            $request->session()->forget($sessionKey);
        }

        /** @var array{
         *     status?: ?string,
         *     search?: ?string,
         *     doctor_id?: ?int,
         *     department_id?: ?int,
         *     date_from?: ?string,
         *     date_to?: ?string,
         *     per_page?: int,
         *     sort_by?: string,
         *     sort_direction?: string
         * }|null $savedFilters */
        $savedFilters = $request->session()->get($sessionKey);

        $statusInput = $request->exists('status')
            ? $request->query('status')
            : ($savedFilters['status'] ?? null);
        $status = $this->normalizeStatus($statusInput, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_ARRIVED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELED,
            Appointment::STATUS_NO_SHOW,
        ]);

        $searchInput = $request->exists('search')
            ? $request->query('search')
            : ($savedFilters['search'] ?? null);
        $search = $this->normalizeNullableString($searchInput);

        $doctorIdInput = $request->exists('doctor_id')
            ? $request->query('doctor_id')
            : ($savedFilters['doctor_id'] ?? null);
        $doctorId = $this->normalizeNullableInteger($doctorIdInput);

        $departmentIdInput = $request->exists('department_id')
            ? $request->query('department_id')
            : ($savedFilters['department_id'] ?? null);
        $departmentId = $this->normalizeNullableInteger($departmentIdInput);

        $dateFromInput = $request->exists('date_from')
            ? $request->query('date_from')
            : ($savedFilters['date_from'] ?? null);
        $dateFrom = $this->normalizeNullableDate($dateFromInput);

        $dateToInput = $request->exists('date_to')
            ? $request->query('date_to')
            : ($savedFilters['date_to'] ?? null);
        $dateTo = $this->normalizeNullableDate($dateToInput);

        $perPageInput = $request->exists('per_page')
            ? $request->query('per_page')
            : ($savedFilters['per_page'] ?? 15);
        $perPage = $this->normalizePerPage($perPageInput);

        $sortByInput = $request->exists('sort_by')
            ? $request->query('sort_by')
            : ($savedFilters['sort_by'] ?? 'scheduled_for');
        $sortBy = $this->normalizeSortBy($sortByInput);

        $sortDirectionInput = $request->exists('sort_direction')
            ? $request->query('sort_direction')
            : ($savedFilters['sort_direction'] ?? 'desc');
        $sortDirection = $this->normalizeSortDirection($sortDirectionInput);

        $filters = [
            'status' => $status,
            'search' => $search,
            'doctor_id' => $doctorId,
            'department_id' => $departmentId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $request->session()->put($sessionKey, $filters);

        return $filters;
    }

    /**
     * @param  array<string>  $allowedStatuses
     */
    private function normalizeStatus(mixed $value, array $allowedStatuses): ?string
    {
        $status = $this->normalizeNullableString($value);

        if ($status === null) {
            return null;
        }

        return in_array($status, $allowedStatuses, true) ? $status : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integerValue = (int) $value;

        return $integerValue > 0 ? $integerValue : null;
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        $date = $this->normalizeNullableString($value);

        if ($date === null) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    private function normalizePerPage(mixed $value): int
    {
        $perPage = (int) $value;
        $allowedPerPageValues = [10, 15, 25, 50];

        return in_array($perPage, $allowedPerPageValues, true) ? $perPage : 15;
    }

    private function normalizeSortBy(mixed $value): string
    {
        $sortBy = trim((string) ($value ?? ''));
        $allowedSortByValues = [
            'appointment_number',
            'scheduled_for',
            'duration_minutes',
            'status',
        ];

        return in_array($sortBy, $allowedSortByValues, true) ? $sortBy : 'scheduled_for';
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = trim((string) ($value ?? ''));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }
}
