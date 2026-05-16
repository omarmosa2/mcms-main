<?php

namespace App\Exports;

use App\Models\Appointment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AppointmentExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private int $clinicId,
        private ?string $status = null,
        private ?string $search = null,
        private ?int $doctorId = null,
    ) {}

    public function query()
    {
        $query = Appointment::query()
            ->where('clinic_id', $this->clinicId)
            ->with(['patient:id,clinic_id,first_name,last_name', 'doctor:id,clinic_id,name'])
            ->orderByDesc('scheduled_for');

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
                    ->where('appointment_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function ($patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    })
                    ->orWhereHas('doctor', function ($doctorQuery) use ($searchTerm): void {
                        $doctorQuery->where('name', 'like', $searchTerm);
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
            'رقم الموعد',
            'اسم المريض',
            'الطبيب',
            'الموعد',
            'المدة (دقيقة)',
            'الحالة',
            'وقت الوصول',
            'وقت الإكمال',
            'وقت الإلغاء',
            'سبب الإلغاء',
            'ملاحظات',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  Appointment  $appointment
     * @return array<int, mixed>
     */
    public function map($appointment): array
    {
        $patientName = $appointment->patient
            ? trim($appointment->patient->first_name.' '.$appointment->patient->last_name)
            : '';

        $doctorName = $appointment->doctor?->name ?? '';

        $statusLabels = [
            'scheduled' => 'مجدول',
            'confirmed' => 'مؤكد',
            'arrived' => 'حاضر',
            'completed' => 'مكتمل',
            'canceled' => 'ملغي',
            'no_show' => 'لم يحضر',
        ];

        return [
            $appointment->appointment_number,
            $patientName,
            $doctorName,
            $appointment->scheduled_for?->format('Y-m-d H:i:s') ?? '',
            $appointment->duration_minutes ?? 30,
            $statusLabels[$appointment->status] ?? $appointment->status,
            $appointment->arrived_at?->format('Y-m-d H:i:s') ?? '',
            $appointment->completed_at?->format('Y-m-d H:i:s') ?? '',
            $appointment->canceled_at?->format('Y-m-d H:i:s') ?? '',
            $appointment->cancel_reason ?? '',
            $appointment->notes ?? '',
            $appointment->created_at?->format('Y-m-d H:i:s') ?? '',
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
