<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  array{
     *     search: ?string,
     *     clinic_id: ?int,
     *     employee_type: ?string,
     *     status: ?string,
     *     education_level: ?string,
     *     hire_date_from: ?string,
     *     hire_date_to: ?string
     * }  $filters
     */
    public function __construct(private array $filters) {}

    public function query(): Builder
    {
        return Employee::query()
            ->withoutClinicScope()
            ->with(['clinic:id,name,code', 'user:id,name,email'])
            ->when($this->filters['clinic_id'] !== null, fn (Builder $query) => $query->where('clinic_id', $this->filters['clinic_id']))
            ->when($this->filters['search'] !== null, function (Builder $query): void {
                $search = $this->filters['search'];

                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%");
                });
            })
            ->when($this->filters['employee_type'] !== null, fn (Builder $query) => $query->where('employee_type', $this->filters['employee_type']))
            ->when($this->filters['status'] !== null, fn (Builder $query) => $query->where('status', $this->filters['status']))
            ->when($this->filters['education_level'] !== null, fn (Builder $query) => $query->where('education_level', $this->filters['education_level']))
            ->when($this->filters['hire_date_from'] !== null, fn (Builder $query) => $query->whereDate('hire_date', '>=', $this->filters['hire_date_from']))
            ->when($this->filters['hire_date_to'] !== null, fn (Builder $query) => $query->whereDate('hire_date', '<=', $this->filters['hire_date_to']))
            ->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'اسم الموظف',
            'العيادة',
            'الجنس',
            'رقم الهاتف',
            'الرقم الوطني',
            'نوع الموظف',
            'الحالة',
            'تاريخ التعيين',
            'الحالة الاجتماعية',
            'المستوى العلمي',
            'اسم الشهادة',
            'اختصاص الشهادة',
            'سنة التخرج',
            'الجهة المانحة',
            'الراتب الأساسي',
            'البدل الإضافي',
            'ملاحظات الراتب',
            'حساب النظام',
        ];
    }

    /**
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        return [
            $employee->full_name,
            $employee->clinic?->name ?? '',
            $this->genderLabel($employee->gender),
            $employee->phone ?? '',
            $employee->national_id ?? '',
            $this->employeeTypeLabel($employee->employee_type),
            $employee->status === Employee::STATUS_ACTIVE ? 'نشط' : 'غير نشط',
            $employee->hire_date?->toDateString() ?? '',
            $this->maritalStatusLabel($employee->marital_status),
            $this->educationLevelLabel($employee->education_level),
            $employee->certificate_name ?? '',
            $employee->education_specialty ?? '',
            $employee->graduation_year ?? '',
            $employee->issuing_institution ?? '',
            (float) $employee->base_salary,
            $employee->additional_allowance !== null ? (float) $employee->additional_allowance : '',
            $employee->salary_notes ?? '',
            $employee->user?->email ?? '',
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

    private function employeeTypeLabel(?string $type): string
    {
        return [
            Employee::TYPE_RECEPTION => 'استقبال',
            Employee::TYPE_NURSE => 'ممرض',
            Employee::TYPE_LAB => 'مخبري',
            Employee::TYPE_USER => 'مستخدم',
            Employee::TYPE_CLEANER => 'عامل نظافة',
            Employee::TYPE_GUARD => 'حارس',
            Employee::TYPE_ACCOUNTANT => 'محاسب',
            Employee::TYPE_ADMINISTRATIVE => 'إداري',
            Employee::TYPE_OTHER => 'أخرى',
        ][$type] ?? (string) $type;
    }

    private function educationLevelLabel(?string $level): string
    {
        return [
            Employee::EDUCATION_NONE => 'بدون شهادة',
            Employee::EDUCATION_SECONDARY => 'ثانوي',
            Employee::EDUCATION_INSTITUTE => 'معهد',
            Employee::EDUCATION_COLLEGE => 'كلية',
            Employee::EDUCATION_POSTGRADUATE => 'دراسات عليا',
            Employee::EDUCATION_OTHER => 'أخرى',
        ][$level] ?? (string) $level;
    }

    private function maritalStatusLabel(?string $status): string
    {
        return [
            Employee::MARITAL_SINGLE => 'أعزب',
            Employee::MARITAL_MARRIED => 'متزوج',
            Employee::MARITAL_DIVORCED => 'مطلق',
            Employee::MARITAL_WIDOWED => 'أرمل',
        ][$status] ?? (string) $status;
    }

    private function genderLabel(?string $gender): string
    {
        return [
            'male' => 'ذكر',
            'female' => 'أنثى',
        ][$gender] ?? (string) $gender;
    }
}
