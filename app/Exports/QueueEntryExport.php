<?php

namespace App\Exports;

use App\Models\QueueEntry;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QueueEntryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private int $clinicId,
        private ?string $status = null,
        private ?string $queueDate = null,
        private ?string $search = null,
        private ?int $doctorId = null,
    ) {}

    public function query()
    {
        $query = QueueEntry::query()
            ->where('clinic_id', $this->clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'appointment:id,clinic_id,appointment_number',
                'assignedDoctor:id,clinic_id,name',
            ])
            ->orderByDesc('queue_date')
            ->orderByDesc('priority')
            ->orderBy('queue_number');

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->queueDate !== null) {
            $query->whereDate('queue_date', $this->queueDate);
        }

        if ($this->doctorId !== null) {
            $query->where(function ($builder) use ($doctorId): void {
                $builder
                    ->where('assigned_doctor_id', $doctorId)
                    ->orWhereHas('visit', function ($visitQuery) use ($doctorId): void {
                        $visitQuery->where('doctor_id', $doctorId);
                    });
            });
        }

        if ($this->search !== null) {
            $searchTerm = '%'.trim($this->search).'%';
            $queueNumberSearch = is_numeric($this->search) ? (int) $this->search : null;

            $query->where(function ($builder) use ($searchTerm, $queueNumberSearch): void {
                $builder
                    ->whereHas('patient', function ($patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    })
                    ->orWhereHas('appointment', function ($appointmentQuery) use ($searchTerm): void {
                        $appointmentQuery->where('appointment_number', 'like', $searchTerm);
                    })
                    ->orWhereHas('assignedDoctor', function ($doctorQuery) use ($searchTerm): void {
                        $doctorQuery->where('name', 'like', $searchTerm);
                    });

                if ($queueNumberSearch !== null) {
                    $builder->orWhere('queue_number', $queueNumberSearch);
                }
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
            'رقم الطابور',
            'اسم المريض',
            'الطبيب المعين',
            'رقم الموعد',
            'تاريخ الطابور',
            'الأولوية',
            'الحالة',
            'وقت تسجيل الوصول',
            'وقت النداء',
            'وقت البدء',
            'وقت الإكمال',
            'ملاحظات',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  QueueEntry  $entry
     * @return array<int, mixed>
     */
    public function map($entry): array
    {
        $patientName = $entry->patient
            ? trim($entry->patient->first_name.' '.$entry->patient->last_name)
            : '';

        $doctorName = $entry->assignedDoctor?->name ?? '';
        $appointmentNumber = $entry->appointment?->appointment_number ?? '';

        $statusLabels = [
            'waiting' => 'في الانتظار',
            'called' => 'تم النداء',
            'in_service' => 'قيد الخدمة',
            'completed' => 'مكتمل',
            'skipped' => 'تم التخطي',
            'canceled' => 'ملغي',
        ];

        return [
            $entry->queue_number,
            $patientName,
            $doctorName,
            $appointmentNumber,
            $entry->queue_date?->format('Y-m-d') ?? '',
            $entry->priority ?? 0,
            $statusLabels[$entry->status] ?? $entry->status,
            $entry->checked_in_at?->format('Y-m-d H:i:s') ?? '',
            $entry->called_at?->format('Y-m-d H:i:s') ?? '',
            $entry->started_at?->format('Y-m-d H:i:s') ?? '',
            $entry->completed_at?->format('Y-m-d H:i:s') ?? '',
            $entry->notes ?? '',
            $entry->created_at?->format('Y-m-d H:i:s') ?? '',
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
