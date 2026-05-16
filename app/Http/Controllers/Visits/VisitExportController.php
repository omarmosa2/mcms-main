<?php

namespace App\Http\Controllers\Visits;

use App\Exports\VisitExport;
use App\Http\Controllers\Controller;
use App\Services\Pdf\PdfExportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitExportController extends Controller
{
    public function __construct(
        private PdfExportService $pdfExportService,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);

        $filename = 'visits_export_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(
            new VisitExport(
                clinicId: $clinicId,
                status: $filters['status'],
                search: $filters['search'],
                doctorId: $filters['doctor_id'],
            ),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);

        $export = new VisitExport(
            clinicId: $clinicId,
            status: $filters['status'],
            search: $filters['search'],
            doctorId: $filters['doctor_id'],
        );

        $filename = 'visits_export_'.now()->format('Y-m-d_His').'.pdf';
        $title = 'تقرير الزيارات';

        return $this->pdfExportService->download($export, $filename, $title);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(403, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array{status: ?string, search: ?string, doctor_id: ?int}
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'doctor_id' => $request->query('doctor_id') ? (int) $request->query('doctor_id') : null,
        ];
    }
}
