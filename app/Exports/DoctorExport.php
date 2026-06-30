<?php

namespace App\Exports;

use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DoctorExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private ?string $search = null,
        private ?int $clinicId = null,
        private ?bool $isActive = null,
    ) {}

    public function query(): Builder
    {
        return DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->with([
                'clinic',
                'schedules' => fn ($query) => $query
                    ->withoutGlobalScope('clinic')
                    ->orderBy('day_of_week'),
            ])
            ->when($this->search !== null, function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->where('full_name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('specialty', 'like', "%{$this->search}%")
                        ->orWhere('username', 'like', "%{$this->search}%");
                });
            })
            ->when($this->clinicId !== null, fn (Builder $query) => $query->where('clinic_id', $this->clinicId))
            ->when($this->isActive !== null, fn (Builder $query) => $query->where('is_active', $this->isActive))
            ->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'اسم الطبيب',
            'العيادة',
            'الاختصاص',
            'الهاتف',
            'البريد الإلكتروني',
            'اسم المستخدم',
            'الجنس',
            'تاريخ بدء العمل',
            'نوع الأجر',
            'قيمة الأجر',
            'العملة',
            'الحالة',
            'اليوم',
            'حالة الدوام',
            'وقت البداية',
            'وقت النهاية',
            'ملاحظات',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  DoctorProfile  $doctor
     * @return array<int, array<int, mixed>>
     */
    public function map($doctor): array
    {
        if ($doctor->schedules->isEmpty()) {
            return [
                $this->doctorRow($doctor, null),
            ];
        }

        return $doctor->schedules
            ->map(fn (DoctorSchedule $schedule): array => $this->doctorRow($doctor, $schedule))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function doctorRow(DoctorProfile $doctor, ?DoctorSchedule $schedule): array
    {
        return [
            $doctor->full_name,
            $doctor->clinic?->name ?? '',
            $doctor->specialty ?? '',
            $doctor->phone ?? '',
            $doctor->email ?? '',
            $doctor->username ?? '',
            $this->genderLabel($doctor->gender),
            $doctor->employment_start_date?->toDateString() ?? '',
            $this->compensationTypeLabel($doctor->compensation_type),
            $doctor->compensationAmount() ?? '',
            $doctor->currency ?? '',
            $doctor->is_active ? 'نشط' : 'غير نشط',
            $schedule !== null ? WeekDay::arabicName((string) $schedule->day_of_week) : 'لا يوجد دوام مسجل',
            $schedule !== null
                ? ($schedule->is_available ? 'دوام' : 'غير متاح')
                : '',
            $this->formatTime($schedule?->start_time),
            $this->formatTime($schedule?->end_time),
            $doctor->notes ?? '',
            $doctor->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    private function compensationTypeLabel(?string $type): string
    {
        return match ($type) {
            DoctorProfile::COMPENSATION_PERCENTAGE => 'نسبة',
            DoctorProfile::COMPENSATION_WEEKLY_FIXED => 'ثابت أسبوعي',
            DoctorProfile::COMPENSATION_MONTHLY_FIXED => 'ثابت شهري',
            default => '',
        };
    }

    private function genderLabel(?string $gender): string
    {
        return match ($gender) {
            DoctorProfile::GENDER_MALE => 'ذكر',
            DoctorProfile::GENDER_FEMALE => 'أنثى',
            default => '',
        };
    }

    private function formatTime(mixed $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return mb_substr((string) $time, 0, 5);
    }
}
