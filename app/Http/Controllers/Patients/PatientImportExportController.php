<?php

namespace App\Http\Controllers\Patients;

use App\Exports\PatientExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientImportRequest;
use App\Jobs\ImportPatientsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientImportExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $filename = 'patients_export_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(
            new PatientExport($clinicId),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function importView(Request $request): InertiaResponse
    {
        $cacheKey = "import:patients:{$request->user()->id}";
        $importStatus = Cache::get($cacheKey);

        return Inertia::render('patients/Import', [
            'import_status' => $importStatus,
        ]);
    }

    public function preview(StorePatientImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('imports/patients/preview', 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $previewData = $this->extractPreview($fullPath);

            return response()->json([
                'success' => true,
                'preview' => $previewData,
                'file_path' => $path,
            ]);
        } catch (\Throwable $e) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في قراءة الملف: '.$e->getMessage(),
            ], 422);
        }
    }

    public function import(StorePatientImportRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $filePath = $request->input('file_path');

        if ($filePath && Storage::disk('local')->exists($filePath)) {
            $path = $filePath;
        } else {
            $file = $request->file('file');
            $path = $file->store('imports/patients', 'local');
        }

        ImportPatientsJob::dispatch(
            (int) $request->user()->id,
            $clinicId,
            $path,
            'local'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Import job queued successfully. Processing will begin shortly.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import job queued. Processing will begin shortly.']);

        return to_route('patients.import');
    }

    public function importStatus(Request $request): JsonResponse
    {
        $cacheKey = "import:patients:{$request->user()->id}";
        $status = Cache::get($cacheKey);

        if ($status === null) {
            return response()->json([
                'status' => 'idle',
                'message' => 'No import job found.',
            ]);
        }

        return response()->json($status);
    }

    /**
     * @return array{headings: array<int, string>, rows: array<int, array<string, mixed>>, total_rows: int, validation_errors: array<int, array{row: int, errors: array<string, string>}>}
     */
    private function extractPreview(string $filePath): array
    {
        $rows = Excel::toCollection(null, $filePath)->first();

        if ($rows === null || $rows->isEmpty()) {
            return [
                'headings' => [],
                'rows' => [],
                'total_rows' => 0,
                'validation_errors' => [],
            ];
        }

        $headings = $rows->shift()->toArray();
        $totalRows = $rows->count();

        $previewRows = $rows->take(50)->map(function ($row) use ($headings) {
            $result = [];
            $values = $row->toArray();

            foreach ($headings as $index => $heading) {
                $result[$heading] = $values[$index] ?? null;
            }

            return $result;
        })->toArray();

        $validationErrors = [];

        foreach ($previewRows as $index => $row) {
            $errors = $this->validateRow($row, $index + 2);

            if (! empty($errors)) {
                $validationErrors[] = [
                    'row' => $index + 2,
                    'errors' => $errors,
                ];
            }
        }

        return [
            'headings' => array_map('strval', $headings),
            'rows' => $previewRows,
            'total_rows' => $totalRows,
            'validation_errors' => $validationErrors,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function validateRow(array $row, int $rowNumber): array
    {
        $errors = [];

        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));

        if ($firstName === '') {
            $errors['first_name'] = 'الاسم الأول مطلوب';
        }

        if ($lastName === '') {
            $errors['last_name'] = 'اسم العائلة مطلوب';
        }

        $email = trim((string) ($row['email'] ?? ''));

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'البريد الإلكتروني غير صالح';
        }

        $gender = trim(strtolower((string) ($row['gender'] ?? '')));

        if ($gender !== '' && ! in_array($gender, ['male', 'female', 'other'], true)) {
            $errors['gender'] = 'الجنس يجب أن يكون male أو female أو other';
        }

        $dob = trim((string) ($row['date_of_birth'] ?? ''));

        if ($dob !== '' && strtotime($dob) === false) {
            $errors['date_of_birth'] = 'تاريخ الميلاد غير صالح';
        }

        return $errors;
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
