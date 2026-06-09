<?php

namespace App\Actions\DailySchedule;

use App\Actions\BaseAction;
use App\Models\BrandingSetting;
use App\Models\ClinicSetting;
use App\Models\ClinicWorkingHour;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListDailyScheduleAction extends BaseAction
{
    private const CARBON_TO_STRING = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    private const DAY_NAMES_AR = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    /**
     * @return array{
     *     date: string,
     *     day_name: string,
     *     day_of_week: int,
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

        $dayOfWeek = (int) $carbonDate->dayOfWeek;
        $dayString = self::CARBON_TO_STRING[$dayOfWeek];
        $dayNameAr = self::DAY_NAMES_AR[$dayOfWeek];

        $branding = BrandingSetting::query()
            ->withoutClinicScope()
            ->where('clinic_id', $clinicId)
            ->first();

        $clinicSettings = ClinicSetting::getGroupSettings($clinicId, 'clinic');

        $departments = $this->getActiveDepartments($clinicId, $dayString, $departmentFilter);

        $clinicData = [];

        foreach ($departments as $department) {
            $clinicHours = ClinicWorkingHour::query()
                ->where('department_id', $department->id)
                ->where('day_of_week', $dayString)
                ->where('is_active', true)
                ->first();

            $doctors = $this->getDoctorsForDepartment($clinicId, $department->id, $dayOfWeek, $doctorFilter);

            if ($doctors->isEmpty()) {
                continue;
            }

            $clinicData[$department->id] = [
                'id' => $department->id,
                'name' => $department->name,
                'clinic_type' => $department->clinic_type,
                'clinic_start_time' => $clinicHours?->start_time,
                'clinic_end_time' => $clinicHours?->end_time,
                'doctors' => $doctors->map(fn (DoctorSchedule $schedule) => [
                    'doctor_id' => $schedule->doctor_id,
                    'doctor_name' => $schedule->doctor?->name,
                    'specialty' => $schedule->doctor?->doctorProfile?->specialty,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ])->values()->all(),
            ];
        }

        return [
            'date' => $carbonDate->format('Y-m-d'),
            'day_name' => $dayNameAr,
            'day_of_week' => $dayOfWeek,
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
    private function getActiveDepartments(int $clinicId, string $dayString, ?int $departmentFilter): Collection
    {
        $query = Department::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('is_active', true);

        if ($departmentFilter !== null) {
            $query->where('id', $departmentFilter);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return Collection<int, DoctorSchedule>
     */
    private function getDoctorsForDepartment(int $clinicId, int $departmentId, int $dayOfWeek, ?int $doctorFilter): Collection
    {
        $doctorIds = DoctorProfile::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('department_id', $departmentId)
            ->where('status', DoctorProfile::STATUS_ACTIVE)
            ->pluck('user_id');

        $query = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->whereIn('doctor_id', $doctorIds)
            ->with(['doctor', 'doctor.doctorProfile']);

        if ($doctorFilter !== null) {
            $query->where('doctor_id', $doctorFilter);
        }

        return $query->orderBy('start_time')->get();
    }
}
