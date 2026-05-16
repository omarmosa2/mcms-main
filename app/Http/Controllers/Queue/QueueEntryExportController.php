<?php

namespace App\Http\Controllers\Queue;

use App\Exports\QueueEntryExport;
use App\Http\Controllers\Controller;
use App\Services\Pdf\PdfExportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueEntryExportController extends Controller
{
    public function __construct(
        private PdfExportService $pdfExportService,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);

        $filename = 'queue_export_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(
            new QueueEntryExport(
                clinicId: $clinicId,
                status: $filters['status'],
                queueDate: $filters['queue_date'],
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

        $export = new QueueEntryExport(
            clinicId: $clinicId,
            status: $filters['status'],
            queueDate: $filters['queue_date'],
            search: $filters['search'],
            doctorId: $filters['doctor_id'],
        );

        $filename = 'queue_export_'.now()->format('Y-m-d_His').'.pdf';
        $title = 'تقرير الطابور';

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
     * @return array{status: ?string, queue_date: ?string, search: ?string, doctor_id: ?int}
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'status' => $request->query('status'),
            'queue_date' => $request->query('queue_date'),
            'search' => $request->query('search'),
            'doctor_id' => $request->query('doctor_id') ? (int) $request->query('doctor_id') : null,
        ];
    }
}
