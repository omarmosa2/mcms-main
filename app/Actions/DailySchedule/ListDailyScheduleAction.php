<?php

namespace App\Actions\DailySchedule;

use App\Actions\BaseAction;
use App\Models\BrandingSetting;
use App\Models\ClinicSetting;
use App\Models\ClinicWorkingHour;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Services\DoctorAvailabilityService;
use App\Support\WeekDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListDailyScheduleAction extends BaseAction
{
    public function __construct(private DoctorAvailabilityService $doctorAvailabilityService) {}

    /**
     * @return array{
     *     date: string,
     *     day_name: string,
     *     day_of_week: string,
     *     branding: array<string, mixed>,
     *     clinic_settings: array<string, mixed>,
     *     clinics: array<int, array<string, mixed>>
     * }
     */
    public function handle(int $clinicId, ?string $date = null, ?int $departmentFilter = null, ?int $doctorFilter = null): array
    {
        $carbonDate = $date !== null && $date !== ''
            ? Carbon::createFromFormat('Y-m-d', $date)
            : Carbon::now();

        $legacyDayOfWeek = (int) $carbonDate->dayOfWeek;
        $dayString = WeekDay::fromCarbonDay($legacyDayOfWeek);

        $branding = BrandingSetting::query()
            ->withoutClinicScope()
            ->where('clinic_id', $clinicId)
            ->first();

        $clinicSettings = ClinicSetting::getGroupSettings($clinicId, 'clinic');

        $departments = $this->getActiveDepartments($clinicId, $departmentFilter);

        $clinicData = [];

        foreach ($departments as $department) {
            $clinicHours = ClinicWorkingHour::query()
                ->where('department_id', $department->id)
                ->where('day_of_week', $dayString)
                ->where('is_active', true)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->first();

            if ($clinicHours === null) {
                continue;
            }

            $doctors = $this->getDoctorsForDepartment(
                clinicId: $clinicId,
                departmentId: (int) $department->id,
                dayString: $dayString,
                legacyDayOfWeek: $legacyDayOfWeek,
                date: $carbonDate,
                doctorFilter: $doctorFilter,
            );

            $clinicData[$department->id] = [
                'id' => $department->id,
                'name' => $department->name,
                'clinic_type' => $department->clinic_type,
                'clinic_start_time' => $this->formatTime($clinicHours->start_time),
                'clinic_end_time' => $this->formatTime($clinicHours->end_time),
                'doctors' => $doctors->values()->all(),
            ];
        }

        return [
            'date' => $carbonDate->format('Y-m-d'),
            'day_name' => WeekDay::arabicName($dayString),
            'day_of_week' => $dayString,
            'formatted_date' => $carbonDate->locale('ar')->isoFormat('D MMMM YYYY'),
            'branding' => [
                'company_name' => $branding?->company_name,
                'logo_path' => $branding?->logo_path,
            ],
            'clinic_settings' => [
                'name' => $clinicSettings['name'] ?? null,
                'phone' => $clinicSettings['phone'] ?? null,
                'address' => $clinicSettings['address'] ?? null,
                'logo_path' => $clinicSettings['logo_path'] ?? null,
            ],
            'clinics' => array_values($clinicData),
        ];
    }

    /**
     * @return Collection<int, Department>
     */
    private function getActiveDepartments(int $clinicId, ?int $departmentFilter): Collection
    {
        return Department::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('is_active', true)
            ->when($departmentFilter !== null, fn ($query) => $query->whereKey($departmentFilter))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getDoctorsForDepartment(
        int $clinicId,
        int $departmentId,
        string $dayString,
        int $legacyDayOfWeek,
        Carbon $date,
        ?int $doctorFilter,
    ): Collection {
        $doctorIds = DoctorProfile::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('department_id', $departmentId)
            ->where('status', DoctorProfile::STATUS_ACTIVE)
            ->pluck('user_id');

        return DoctorSchedule::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->whereIn('day_of_week', [$dayString, (string) $legacyDayOfWeek])
            ->where('is_available', true)
            ->whereIn('doctor_id', $doctorIds)
            ->when($doctorFilter !== null, fn ($query) => $query->where('doctor_id', $doctorFilter))
            ->with(['doctor', 'doctor.doctorProfile'])
            ->orderBy('start_time')
            ->get()
            ->map(function (DoctorSchedule $schedule) use ($clinicId, $departmentId, $date): ?array {
                $availability = $this->doctorAvailabilityService->availabilityForDay(
                    clinicId: $clinicId,
                    doctorId: (int) $schedule->doctor_id,
                    date: $date,
                    departmentId: $departmentId,
                );

                if ($availability['unavailable_all_day'] || ! $availability['is_available']) {
                    return null;
                }

                return [
                    'doctor_id' => $schedule->doctor_id,
                    'doctor_name' => $schedule->doctor?->name,
                    'specialty' => $schedule->doctor?->doctorProfile?->specialty,
                    'start_time' => $availability['available_periods'][0]['start_time'] ?? $this->formatTime($schedule->start_time),
                    'end_time' => $availability['available_periods'][array_key_last($availability['available_periods'])]['end_time'] ?? $this->formatTime($schedule->end_time),
                    'available_periods' => $availability['available_periods'],
                    'unavailable_periods' => $availability['unavailable_periods'],
                ];
            })
            ->filter()
            ->values();
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr((string) $time, 0, 5);
    }
}
