<?php

namespace App\Exports;

use App\Models\Invoice;
use App\Support\MoneyFormatter;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoiceExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private int $clinicId,
        private ?string $status = null,
        private ?int $patientId = null,
        private ?string $search = null,
    ) {}

    public function query()
    {
        $query = Invoice::query()
            ->where('clinic_id', $this->clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'items:id,clinic_id,invoice_id,description,line_total',
                'payments:id,clinic_id,invoice_id,amount,status,paid_at',
            ])
            ->orderByDesc('id');

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->patientId !== null) {
            $query->where('patient_id', $this->patientId);
        }

        if ($this->search !== null) {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where(function ($builder) use ($searchTerm): void {
                $builder
                    ->where('invoice_number', 'like', $searchTerm)
                    ->orWhereHas('patient', function ($patientQuery) use ($searchTerm): void {
                        $patientQuery
                            ->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
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
            'رقم الفاتورة',
            'اسم المريض',
            'الحالة',
            'المبلغ الفرعي',
            'الخصم',
            'الضريبة',
            'الإجمالي',
            'المدفوع',
            'المتبقي',
            'تاريخ الإصدار',
            'تاريخ الاستحقاق',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  Invoice  $invoice
     * @return array<int, mixed>
     */
    public function map($invoice): array
    {
        $patientName = $invoice->patient
            ? trim($invoice->patient->first_name.' '.$invoice->patient->last_name)
            : '';

        $statusLabels = [
            'draft' => 'مسودة',
            'issued' => 'صادرة',
            'partially_paid' => 'مدفوعة جزئياً',
            'paid' => 'مدفوعة',
            'void' => 'ملغاة',
        ];

        return [
            $invoice->invoice_number,
            $patientName,
            $statusLabels[$invoice->status] ?? $invoice->status,
            MoneyFormatter::formatForClinic($invoice->subtotal_amount, $this->clinicId),
            MoneyFormatter::formatForClinic($invoice->discount_amount, $this->clinicId),
            MoneyFormatter::formatForClinic($invoice->tax_amount, $this->clinicId),
            MoneyFormatter::formatForClinic($invoice->total_amount, $this->clinicId),
            MoneyFormatter::formatForClinic($invoice->paid_amount, $this->clinicId),
            MoneyFormatter::formatForClinic($invoice->balance_amount, $this->clinicId),
            $invoice->issued_at?->format('Y-m-d H:i:s') ?? '',
            $invoice->due_at?->format('Y-m-d') ?? '',
            $invoice->created_at?->format('Y-m-d H:i:s') ?? '',
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
