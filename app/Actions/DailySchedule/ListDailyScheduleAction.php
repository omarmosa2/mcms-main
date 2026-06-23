<?php

namespace App\Actions\DailySchedule;

use App\Actions\BaseAction;
use App\Models\BrandingSetting;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
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
     *     day_of_week: int,
     *     branding: array<string, mixed>,
     *     clinic_settings: array<string, mixed>,
     *     clinics: array<int, array<string, mixed>>
     * }
     */
    public function handle(?string $date = null, ?int $clinicFilter = null, ?int $doctorFilter = null): array
    {
        $carbonDate = $date !== null && $date !== ''
            ? Carbon::createFromFormat('Y-m-d', $date)
            : Carbon::now();

        $dayOfWeek = (int) $carbonDate->dayOfWeek;

        $branding = BrandingSetting::query()
            ->withoutClinicScope()
            ->first();

        $clinicSettings = [];

        $clinics = $this->getActiveClinics($clinicFilter);

        $clinicData = [];

        foreach ($clinics as $clinic) {
            $clinicHours = ClinicWorkingHour::query()
                ->where('clinic_id', $clinic->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->first();

            if ($clinicHours === null) {
                continue;
            }

            $doctors = $this->getDoctorsForClinic(
                clinicId: (int) $clinic->id,
                dayOfWeek: $dayOfWeek,
                date: $carbonDate,
                doctorFilter: $doctorFilter,
            );

            $clinicData[$clinic->id] = [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'clinic_type' => null,
                'clinic_start_time' => $this->formatTime($clinicHours->start_time),
                'clinic_end_time' => $this->formatTime($clinicHours->end_time),
                'doctors' => $doctors->values()->all(),
            ];
        }

        return [
            'date' => $carbonDate->format('Y-m-d'),
            'day_name' => WeekDay::arabicName((string) $dayOfWeek),
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
     * @return Collection<int, Clinic>
     */
    private function getActiveClinics(?int $clinicFilter): Collection
    {
        $query = Clinic::query()
            ->clinical()
            ->where('is_active', true);

        if ($clinicFilter !== null) {
            $query->whereKey($clinicFilter);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getDoctorsForClinic(
        int $clinicId,
        int $dayOfWeek,
        Carbon $date,
        ?int $doctorFilter,
    ): Collection {
        $doctorProfiles = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->when($doctorFilter !== null, fn ($query) => $query->where('user_id', $doctorFilter))
            ->with('user:id,name')
            ->get(['id', 'user_id', 'specialty'])
            ->keyBy('id');

        return DoctorSchedule::query()
            ->withoutGlobalScope('clinic')
            ->forClinic($clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->whereIn('doctor_profile_id', $doctorProfiles->keys())
            ->orderBy('start_time')
            ->get()
            ->map(function (DoctorSchedule $schedule) use ($clinicId, $date, $doctorProfiles): ?array {
                $profile = $doctorProfiles->get($schedule->doctor_profile_id);

                if ($profile === null) {
                    return null;
                }

                $availability = $this->doctorAvailabilityService->availabilityForDay(
                    clinicId: $clinicId,
                    doctorId: (int) $profile->user_id,
                    date: $date,
                );

                if ($availability['unavailable_all_day'] || ! $availability['is_available']) {
                    return null;
                }

                return [
                    'doctor_id' => $profile->user_id,
                    'doctor_name' => $profile->user?->name,
                    'specialty' => $profile->specialty,
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
