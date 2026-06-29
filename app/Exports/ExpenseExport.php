<?php

namespace App\Exports;

use App\Models\Expense;
use App\Support\MoneyFormatter;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private ?int $clinicId = null,
        private ?string $status = null,
        private ?int $categoryId = null,
        private ?string $dateFrom = null,
        private ?string $dateTo = null,
        private ?string $paymentMethod = null,
        private ?string $search = null,
    ) {}

    public function query()
    {
        $query = Expense::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed()
            ->with(['category:id,name', 'clinic:id,name', 'creator:id,name'])
            ->orderByDesc('expense_date');

        if ($this->clinicId !== null) {
            $query->where(function ($q) {
                $q->where('clinic_id', $this->clinicId)
                    ->orWhereNull('clinic_id');
            });
        }

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->categoryId !== null) {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->dateFrom !== null) {
            $query->where('expense_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== null) {
            $query->where('expense_date', '<=', $this->dateTo);
        }

        if ($this->paymentMethod !== null) {
            $query->where('payment_method', $this->paymentMethod);
        }

        if ($this->search !== null) {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhere('paid_to', 'like', $searchTerm)
                    ->orWhere('reference_number', 'like', $searchTerm)
                    ->orWhere('expense_number', 'like', $searchTerm);
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
            'رقم المصروف',
            'التاريخ',
            'العنوان',
            'التصنيف',
            'العيادة',
            'المبلغ',
            'طريقة الدفع',
            'الجهة المستلمة',
            'الرقم المرجعي',
            'الحالة',
            'أضيف بواسطة',
            'ملاحظات',
        ];
    }

    /**
     * @param  Expense  $expense
     * @return array<int, mixed>
     */
    public function map($expense): array
    {
        $statusLabels = Expense::statusLabels();
        $paymentLabels = Expense::paymentMethodLabels();

        return [
            $expense->expense_number,
            $expense->expense_date?->format('Y-m-d') ?? '',
            $expense->title ?? $expense->description,
            $expense->category?->name ?? '-',
            $expense->clinic?->name ?? 'عام',
            MoneyFormatter::format($expense->amount),
            $paymentLabels[$expense->payment_method] ?? ($expense->payment_method ?? '-'),
            $expense->paid_to ?? '-',
            $expense->reference_number ?? '-',
            $statusLabels[$expense->status] ?? $expense->status,
            $expense->creator?->name ?? ($expense->user?->name ?? '-'),
            $expense->description ?? '',
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
