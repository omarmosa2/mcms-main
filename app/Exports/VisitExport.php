<?php

namespace App\Exports;

use App\Models\Visit;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VisitExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private int $clinicId,
        private ?string $status = null,
        private ?string $search = null,
        private ?int $doctorId = null,
    ) {}

    public function query()
    {
        $query = Visit::query()
            ->where('clinic_id', $this->clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'doctor:id,clinic_id,name',
                'appointment:id,clinic_id,appointment_number',
                'queueEntry:id,clinic_id,queue_number,queue_date,status',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->doctorId !== null) {
            $query->where('doctor_id', $this->doctorId);
        }

        if ($this->search !== null) {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where(function ($builder) use ($searchTerm): void {
                $builder
                    ->where('visit_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function ($patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    })
                    ->orWhereHas('doctor', function ($doctorQuery) use ($searchTerm): void {
                        $doctorQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('appointment', function ($appointmentQuery) use ($searchTerm): void {
                        $appointmentQuery->where('appointment_number', 'like', $searchTerm);
                    });
            });
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'رقم الزيارة',
            'اسم المريض',
            'الطبيب',
            'رقم الموعد',
            'رقم الطابور',
            'الحالة',
            'وقت البدء',
            'وقت قيد التنفيذ',
            'وقت الإكمال',
            'الشكوى الرئيسية',
            'ملاحظات سريرية',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  Visit  $visit
     * @return array<int, mixed>
     */
    public function map($visit): array
    {
        $patientName = $visit->patient
            ? trim($visit->patient->first_name.' '.$visit->patient->last_name)
            : '';

        $doctorName = $visit->doctor?->name ?? '';
        $appointmentNumber = $visit->appointment?->appointment_number ?? '';
        $queueNumber = $visit->queueEntry?->queue_number ?? '';

        $statusLabels = [
            'started' => 'بدأت',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتملة',
        ];

        return [
            $visit->visit_number,
            $patientName,
            $doctorName,
            $appointmentNumber,
            $queueNumber,
            $statusLabels[$visit->status] ?? $visit->status,
            $visit->started_at?->format('Y-m-d H:i:s') ?? '',
            $visit->in_progress_at?->format('Y-m-d H:i:s') ?? '',
            $visit->completed_at?->format('Y-m-d H:i:s') ?? '',
            $visit->chief_complaint ?? '',
            $visit->clinical_notes ?? '',
            $visit->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
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
}
