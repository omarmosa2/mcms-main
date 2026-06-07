<?php

namespace App\Imports;

use App\Models\Patient;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PatientImport implements ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    public int $importedCount = 0;

    public int $failedCount = 0;

    /** @var array<int, array{row: int, errors: array<string, string>}> */
    public array $errors = [];

    private int $currentRow = 0;

    public function __construct(
        private int $clinicId,
        private int $userId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->currentRow++;

            try {
                $data = $this->normalizeRow($row);

                $existing = Patient::query()
                    ->where('clinic_id', $this->clinicId)
                    ->where(function ($query) use ($data): void {
                        if (isset($data['file_number']) && $data['file_number'] > 0) {
                            $query->where('file_number', $data['file_number']);
                        }
                        if (isset($data['national_id_hash']) && $data['national_id_hash'] !== '') {
                            $query->orWhere('national_id_hash', $data['national_id_hash']);
                        }
                    })
                    ->exists();

                if ($existing) {
                    $this->failedCount++;
                    $this->errors[] = [
                        'row' => $this->currentRow,
                        'errors' => ['file_number' => 'Duplicate patient (file number or national ID already exists).'],
                    ];

                    continue;
                }

                Patient::create([
                    ...$data,
                    'clinic_id' => $this->clinicId,
                ]);

                $this->importedCount++;
            } catch (\Throwable $e) {
                $this->failedCount++;
                $this->errors[] = [
                    'row' => $this->currentRow,
                    'errors' => ['general' => $e->getMessage()],
                ];
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|string|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'national_id' => 'nullable|string|max:100',
            'file_number' => 'nullable|integer|min:1',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function customValidationMessages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.email' => 'Email must be a valid email address.',
            'gender.in' => 'Gender must be male, female, or other.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $data = [];

        $firstName = $this->normalizeString($row['first_name'] ?? '');
        $lastName = $this->normalizeString($row['last_name'] ?? '');
        $fileNumber = $row['file_number'] ?? null;

        $data['first_name'] = $firstName;
        $data['last_name'] = $lastName;
        $data['file_number'] = ($fileNumber !== null && (int) $fileNumber > 0) ? (int) $fileNumber : $this->generateFileNumber();

        if (isset($row['date_of_birth']) && $this->normalizeString($row['date_of_birth']) !== '') {
            $data['date_of_birth'] = $this->normalizeDate($row['date_of_birth']);
        }

        if (isset($row['gender']) && in_array(strtolower((string) $row['gender']), ['male', 'female', 'other'], true)) {
            $data['gender'] = strtolower((string) $row['gender']);
        }

        if (isset($row['phone']) && $this->normalizeString($row['phone']) !== '') {
            $data['phone'] = $this->normalizeString($row['phone']);
        }

        if (isset($row['email']) && $this->normalizeString($row['email']) !== '') {
            $data['email'] = strtolower($this->normalizeString($row['email']));
        }

        if (isset($row['national_id']) && $this->normalizeString($row['national_id']) !== '') {
            $data['national_id'] = $this->normalizeString($row['national_id']);
            $data['national_id_hash'] = Patient::hashNationalId($data['national_id']);
        }

        if (isset($row['emergency_contact_name']) && $this->normalizeString($row['emergency_contact_name']) !== '') {
            $data['emergency_contact_name'] = $this->normalizeString($row['emergency_contact_name']);
        }

        if (isset($row['emergency_contact_phone']) && $this->normalizeString($row['emergency_contact_phone']) !== '') {
            $data['emergency_contact_phone'] = $this->normalizeString($row['emergency_contact_phone']);
        }

        if (isset($row['notes']) && $this->normalizeString($row['notes']) !== '') {
            $data['notes'] = $this->normalizeString($row['notes']);
        }

        return $data;
    }

    private function normalizeString(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function normalizeDate(mixed $value): string
    {
        $date = $this->normalizeString($value);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            $parts = explode('/', $date);

            return $parts[2].'-'.$parts[1].'-'.$parts[0];
        }

        return $date;
    }

    private function generateFileNumber(): int
    {
        $maxFileNumber = (int) Patient::query()->max('file_number');

        return $maxFileNumber + 1;
    }
}
