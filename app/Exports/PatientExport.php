<?php

namespace App\Exports;

use App\Models\Patient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PatientExport implements FromQuery, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        private int $clinicId,
    ) {}

    public function query()
    {
        return Patient::query()
            ->where('clinic_id', $this->clinicId)
            ->orderBy('created_at', 'desc')
            ->select('file_number', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'email', 'national_id', 'emergency_contact_name', 'emergency_contact_phone', 'notes', 'created_at');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'File Number',
            'First Name',
            'Last Name',
            'Date of Birth',
            'Gender',
            'Phone',
            'Email',
            'National ID',
            'Emergency Contact Name',
            'Emergency Contact Phone',
            'Notes',
            'Created At',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function map($patient): array
    {
        $nationalId = null;

        if ($patient->national_id !== null) {
            try {
                $nationalId = Crypt::decryptString($patient->national_id);
            } catch (DecryptException) {
                $nationalId = '[encrypted]';
            }
        }

        return [
            $patient->file_number,
            $patient->first_name,
            $patient->last_name,
            $patient->date_of_birth?->format('Y-m-d') ?? '',
            $patient->gender ?? '',
            $patient->phone ?? '',
            $patient->email ?? '',
            $nationalId ?? '',
            $patient->emergency_contact_name ?? '',
            $patient->emergency_contact_phone ?? '',
            $patient->notes ?? '',
            $patient->created_at?->format('Y-m-d H:i:s') ?? '',
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
