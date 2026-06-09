<?php

namespace App\Http\Controllers\DailySchedule;

use App\Actions\DailySchedule\ListDailyScheduleAction;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DoctorProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class DailyScheduleController extends Controller
{
    public function __construct(
        private ListDailyScheduleAction $listDailyScheduleAction,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $date = $request->query('date');
        $departmentFilter = $request->exists('department_id') && $request->query('department_id') !== ''
            ? (int) $request->query('department_id')
            : null;
        $doctorFilter = $request->exists('doctor_id') && $request->query('doctor_id') !== ''
            ? (int) $request->query('doctor_id')
            : null;

        $scheduleData = $this->listDailyScheduleAction->handle(
            clinicId: $clinicId,
            date: is_string($date) ? $date : null,
            departmentFilter: $departmentFilter,
            doctorFilter: $doctorFilter,
        );

        $departments = Department::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $doctorProfiles = DoctorProfile::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('status', DoctorProfile::STATUS_ACTIVE)
            ->with('user')
            ->get()
            ->map(fn (DoctorProfile $profile) => [
                'id' => $profile->user_id,
                'name' => $profile->user?->name,
            ])
            ->filter(fn (array $doctor) => $doctor['name'] !== null)
            ->values();

        return Inertia::render('daily-schedule/Index', [
            'scheduleData' => $scheduleData,
            'departments' => $departments,
            'doctors' => $doctorProfiles,
            'filters' => [
                'date' => $scheduleData['date'],
                'department_id' => $departmentFilter,
                'doctor_id' => $doctorFilter,
            ],
        ]);
    }

    public function display(Request $request): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $scheduleData = $this->listDailyScheduleAction->handle(
            clinicId: $clinicId,
        );

        return Inertia::render('daily-schedule/Display', [
            'scheduleData' => $scheduleData,
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
