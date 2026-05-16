<?php

namespace App\Http\Controllers\Billing;

use App\Exports\InvoiceExport;
use App\Http\Controllers\Controller;
use App\Services\Pdf\PdfExportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceExportController extends Controller
{
    public function __construct(
        private PdfExportService $pdfExportService,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);

        $filename = 'invoices_export_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(
            new InvoiceExport(
                clinicId: $clinicId,
                status: $filters['status'],
                patientId: $filters['patient_id'],
                search: $filters['search'],
            ),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);

        $export = new InvoiceExport(
            clinicId: $clinicId,
            status: $filters['status'],
            patientId: $filters['patient_id'],
            search: $filters['search'],
        );

        $filename = 'invoices_export_'.now()->format('Y-m-d_His').'.pdf';
        $title = 'تقرير الفواتير';

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
     * @return array{status: ?string, patient_id: ?int, search: ?string}
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'status' => $request->query('status'),
            'patient_id' => $request->query('patient_id') ? (int) $request->query('patient_id') : null,
            'search' => $request->query('search'),
        ];
    }
}
