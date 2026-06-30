<?php

namespace App\Exports;

use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Support\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClinicExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private ?string $search = null,
        private ?bool $isActive = null,
        private string $sortBy = 'created_at',
        private string $sortDirection = 'desc',
    ) {}

    public function query(): Builder
    {
        $query = Clinic::query()
            ->clinical()
            ->with(['workingHours' => function ($query): void {
                $query->orderBy('day_of_week');
            }])
            ->withCount('employees');

        if ($this->isActive !== null) {
            $query->where('is_active', $this->isActive);
        }

        if ($this->search !== null) {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where(function (Builder $builder) use ($searchTerm): void {
                $builder->where('name', 'like', $searchTerm)
                    ->orWhere('code', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        $this->applySorting($query);

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'اسم العيادة',
            'رمز العيادة',
            'الحالة',
            'عدد الموظفين',
            'اليوم',
            'حالة الدوام',
            'وقت البداية',
            'وقت النهاية',
            'الوصف',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  Clinic  $clinic
     * @return array<int, array<int, mixed>>
     */
    public function map($clinic): array
    {
        if ($clinic->workingHours->isEmpty()) {
            return [
                $this->clinicRow($clinic, null),
            ];
        }

        return $clinic->workingHours
            ->map(fn (ClinicWorkingHour $workingHour): array => $this->clinicRow($clinic, $workingHour))
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

    private function applySorting(Builder $query): void
    {
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        if ($this->sortBy === 'name') {
            $query->orderBy('name', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($this->sortBy === 'code') {
            $query->orderBy('code', $direction)->orderBy('id', 'desc');

            return;
        }

        if ($this->sortBy === 'is_active') {
            $query->orderBy('is_active', $direction)->orderBy('name');

            return;
        }

        if ($this->sortBy === 'employees_count') {
            $query->orderBy('employees_count', $direction)->orderBy('name');

            return;
        }

        $query->orderBy('created_at', $direction)->orderBy('id', 'desc');
    }

    /**
     * @return array<int, mixed>
     */
    private function clinicRow(Clinic $clinic, ?ClinicWorkingHour $workingHour): array
    {
        return [
            $clinic->name,
            $clinic->code ?? '',
            $clinic->is_active ? 'فعالة' : 'غير فعالة',
            $clinic->employees_count ?? 0,
            $workingHour !== null ? WeekDay::arabicName((string) $workingHour->day_of_week) : 'لا يوجد دوام مسجل',
            $workingHour !== null
                ? ($workingHour->is_active ? 'دوام' : 'مغلق')
                : '',
            $this->formatTime($workingHour?->start_time),
            $this->formatTime($workingHour?->end_time),
            $clinic->description ?? '',
            $clinic->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    private function formatTime(mixed $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return mb_substr((string) $time, 0, 5);
    }
}
