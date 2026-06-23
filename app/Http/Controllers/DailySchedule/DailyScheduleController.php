<?php

namespace App\Http\Controllers\DailySchedule;

use App\Actions\DailySchedule\ListDailyScheduleAction;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DailyScheduleController extends Controller
{
    public function __construct(
        private ListDailyScheduleAction $listDailyScheduleAction,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $date = $request->query('date');
        $clinicFilter = $request->exists('clinic_id') && $request->query('clinic_id') !== ''
            ? (int) $request->query('clinic_id')
            : null;
        $doctorFilter = $request->exists('doctor_id') && $request->query('doctor_id') !== ''
            ? (int) $request->query('doctor_id')
            : null;

        $scheduleData = $this->listDailyScheduleAction->handle(
            date: is_string($date) ? $date : null,
            clinicFilter: $clinicFilter,
            doctorFilter: $doctorFilter,
        );

        $allClinics = Clinic::query()
            ->clinical()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $doctorProfiles = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
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
            'clinics' => $allClinics,
            'doctors' => $doctorProfiles,
            'filters' => [
                'date' => $scheduleData['date'],
                'clinic_id' => $clinicFilter,
                'doctor_id' => $doctorFilter,
            ],
        ]);
    }

    public function display(Request $request): InertiaResponse
    {
        $scheduleData = $this->listDailyScheduleAction->handle();

        return Inertia::render('daily-schedule/Display', [
            'scheduleData' => $scheduleData,
        ]);
    }
}
