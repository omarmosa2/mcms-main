<?php

namespace App\Http\Controllers\DoctorSchedules;

use App\Actions\DoctorSchedules\CreateDoctorScheduleAction;
use App\Actions\DoctorSchedules\DeleteDoctorScheduleAction;
use App\Actions\DoctorSchedules\ListDoctorSchedulesAction;
use App\Actions\DoctorSchedules\UpdateDoctorScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorSchedules\StoreDoctorScheduleRequest;
use App\Http\Requests\DoctorSchedules\UpdateDoctorScheduleRequest;
use App\Http\Resources\DoctorScheduleResource;
use App\Models\ClinicWorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DoctorScheduleController extends Controller
{
    public function __construct(
        private ListDoctorSchedulesAction $listDoctorSchedulesAction,
        private CreateDoctorScheduleAction $createDoctorScheduleAction,
        private UpdateDoctorScheduleAction $updateDoctorScheduleAction,
        private DeleteDoctorScheduleAction $deleteDoctorScheduleAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveIndexFilters($request);

        $schedules = $this->listDoctorSchedulesAction->handle(
            clinicId: $clinicId,
            filters: $filters,
        );

        $schedulesResource = DoctorScheduleResource::collection($schedules);

        if ($request->expectsJson()) {
            return $schedulesResource;
        }

        return Inertia::render('doctor-schedules/Index', [
            'schedules' => $schedulesResource->response()->getData(true),
            'filters' => $filters,
        ]);
    }

    public function store(StoreDoctorScheduleRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $schedule = $this->createDoctorScheduleAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return DoctorScheduleResource::make($schedule)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم إنشاء جدول الدوام بنجاح.']);

        return to_route('doctor-schedules.index');
    }

    public function update(
        UpdateDoctorScheduleRequest $request,
        int $doctorScheduleId,
    ): DoctorScheduleResource|RedirectResponse {
        $clinicId = $this->resolveClinicId($request);

        $schedule = $this->updateDoctorScheduleAction->handle(
            clinicId: $clinicId,
            scheduleId: $doctorScheduleId,
            userId: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return DoctorScheduleResource::make($schedule);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث جدول الدوام بنجاح.']);

        return to_route('doctor-schedules.index');
    }

    public function destroy(Request $request, int $doctorScheduleId): Response|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $this->deleteDoctorScheduleAction->handle(
            clinicId: $clinicId,
            scheduleId: $doctorScheduleId,
            userId: (int) $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف جدول الدوام بنجاح.']);

        return to_route('doctor-schedules.index');
    }

    public function clinicHours(Request $request): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $dayOfWeek = (int) $request->query('day_of_week', 0);

        $dayStrings = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ];

        $dayString = $dayStrings[$dayOfWeek] ?? 'sunday';

        $clinicHour = ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $dayString)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'start_time' => $clinicHour?->start_time,
            'end_time' => $clinicHour?->end_time,
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

    /**
     * @return array{
     *     doctor_id: ?int,
     *     is_available: ?bool,
     *     per_page: int
     * }
     */
    private function resolveIndexFilters(Request $request): array
    {
        $doctorId = $request->exists('doctor_id')
            ? (int) $request->query('doctor_id')
            : null;

        $isAvailable = $request->exists('is_available')
            ? filter_var($request->query('is_available'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $perPage = (int) $request->query('per_page', 20);
        $perPage = in_array($perPage, [10, 15, 20, 25, 50], true) ? $perPage : 20;

        return [
            'doctor_id' => $doctorId,
            'is_available' => $isAvailable,
            'per_page' => $perPage,
        ];
    }
}
