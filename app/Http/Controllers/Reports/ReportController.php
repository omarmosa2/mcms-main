<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\GetClinicalDiagnosticsReportAction;
use App\Actions\Reports\GetDoctorPerformanceReportAction;
use App\Actions\Reports\GetFinancialReportAction;
use App\Actions\Reports\GetFinancialStatementsReportAction;
use App\Actions\Reports\GetOperationalReportAction;
use App\Http\Controllers\Controller;
use App\Support\MinimalPdfDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private GetOperationalReportAction $getOperationalReportAction,
        private GetFinancialReportAction $getFinancialReportAction,
        private GetDoctorPerformanceReportAction $getDoctorPerformanceReportAction,
        private GetClinicalDiagnosticsReportAction $getClinicalDiagnosticsReportAction,
        private GetFinancialStatementsReportAction $getFinancialStatementsReportAction,
    ) {}

    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $payload = $this->buildPayload($request);

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('reports/Index', $payload);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $payload = $this->buildPayload($request);
        $this->ensureReportExportAuthorized($payload);
        $filename = sprintf('reports-export-%s.xls', now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($payload): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['MCMS Reports Export']);
            fputcsv($output, ['Generated At', now()->toDateTimeString()]);
            fputcsv($output, ['From', $payload['filters']['from']]);
            fputcsv($output, ['To', $payload['filters']['to']]);
            fputcsv($output, []);

            if ($payload['operational_summary'] !== null) {
                $this->writeOperationalSummaryRows($output, $payload['operational_summary']);
                fputcsv($output, []);
            }

            if ($payload['financial_summary'] !== null) {
                $this->writeFinancialSummaryRows($output, $payload['financial_summary']);
                fputcsv($output, []);
            }

            if ($payload['doctor_performance'] !== null) {
                $this->writeDoctorPerformanceRows($output, $payload['doctor_performance']);
                fputcsv($output, []);
            }

            if ($payload['diagnostics_summary'] !== null) {
                $this->writeDiagnosticsRows($output, $payload['diagnostics_summary']);
                fputcsv($output, []);
            }

            if ($payload['financial_statements'] !== null) {
                $this->writeFinancialStatementsRows($output, $payload['financial_statements']);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $payload = $this->buildPayload($request);
        $this->ensureReportExportAuthorized($payload);

        $lines = [
            'MCMS Reports Export',
            sprintf('Generated At: %s', now()->toDateTimeString()),
            sprintf('From: %s', (string) ($payload['filters']['from'] ?? '')),
            sprintf('To: %s', (string) ($payload['filters']['to'] ?? '')),
            '',
        ];

        if ($payload['operational_summary'] !== null) {
            $operational = $payload['operational_summary'];
            $lines[] = 'Operational Summary';
            $lines[] = sprintf('Patients Total: %d', (int) ($operational['patients_total'] ?? 0));
            $lines[] = sprintf('Appointments Total: %d', (int) ($operational['appointments']['total'] ?? 0));
            $lines[] = sprintf('Queue Entries Total: %d', (int) ($operational['queue_entries']['total'] ?? 0));
            $lines[] = sprintf('Visits Total: %d', (int) ($operational['visits']['total'] ?? 0));
            $lines[] = '';
        }

        if ($payload['financial_summary'] !== null) {
            $financial = $payload['financial_summary'];
            $lines[] = 'Financial Summary';
            $lines[] = sprintf('Invoices Count: %d', (int) ($financial['invoices']['count'] ?? 0));
            $lines[] = sprintf('Total Invoiced: %.2f', (float) ($financial['invoices']['total_amount'] ?? 0));
            $lines[] = sprintf('Gross Collections: %.2f', (float) ($financial['payments']['gross_collections'] ?? 0));
            $lines[] = sprintf('Refund Amount: %.2f', (float) ($financial['payments']['refund_amount'] ?? 0));
            $lines[] = sprintf('Net Collections: %.2f', (float) ($financial['payments']['net_collections'] ?? 0));
            $lines[] = '';
        }

        if ($payload['doctor_performance'] !== null) {
            $doctorPerformance = $payload['doctor_performance'];
            $lines[] = 'Doctor Performance';
            $lines[] = sprintf('Doctors Count: %d', (int) ($doctorPerformance['doctors_count'] ?? 0));
            $lines[] = sprintf('Visits Total: %d', (int) ($doctorPerformance['totals']['visits'] ?? 0));
            $lines[] = sprintf('Unique Patients: %d', (int) ($doctorPerformance['totals']['unique_patients'] ?? 0));
            $lines[] = sprintf('Revenue Amount: %.2f', (float) ($doctorPerformance['totals']['revenue_amount'] ?? 0));
            $lines[] = '';
        }

        if ($payload['diagnostics_summary'] !== null) {
            $diagnostics = $payload['diagnostics_summary'];
            $lines[] = 'Diagnostics Summary';
            $lines[] = sprintf('Lab Orders Total: %d', (int) ($diagnostics['lab']['orders_total'] ?? 0));
            $lines[] = sprintf('Lab Resulted Total: %d', (int) ($diagnostics['lab']['resulted_total'] ?? 0));
            $lines[] = sprintf('Radiology Orders Total: %d', (int) ($diagnostics['radiology']['orders_total'] ?? 0));
            $lines[] = sprintf('Radiology Reported Total: %d', (int) ($diagnostics['radiology']['reported_total'] ?? 0));
            $lines[] = '';
        }

        if ($payload['financial_statements'] !== null) {
            $statements = $payload['financial_statements'];
            $lines[] = 'Financial Statements';
            $lines[] = sprintf('Net Income: %.2f', (float) ($statements['income_statement']['net_income'] ?? 0));
            $lines[] = sprintf('Assets: %.2f', (float) ($statements['balance_sheet']['assets'] ?? 0));
            $lines[] = sprintf('Liabilities: %.2f', (float) ($statements['balance_sheet']['liabilities'] ?? 0));
            $lines[] = sprintf('Equity: %.2f', (float) ($statements['balance_sheet']['equity'] ?? 0));
            $lines[] = sprintf('Net Cashflow: %.2f', (float) ($statements['cash_flow']['net_cashflow'] ?? 0));
        }

        $pdf = MinimalPdfDocument::build($lines);

        return response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="reports-export-%s.pdf"', now()->format('Ymd-His')),
        ]);
    }

    /**
     * @return array{
     *     filters: array{from: ?string, to: ?string},
     *     can_view_operational: bool,
     *     can_view_financial: bool,
     *     operational_summary: array<string, mixed>|null,
     *     financial_summary: array<string, mixed>|null,
     *     doctor_performance: array<string, mixed>|null,
     *     diagnostics_summary: array<string, mixed>|null,
     *     financial_statements: array<string, mixed>|null
     * }
     */
    private function buildPayload(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $clinicId = $this->resolveClinicId($request);
        $user = $request->user();
        $fromDate = isset($validated['from']) ? (string) $validated['from'] : null;
        $toDate = isset($validated['to']) ? (string) $validated['to'] : null;
        $canViewOperational = $user !== null && $user->hasPermission('reports.view');
        $canViewFinancial = $user !== null && $user->hasPermission('reports.financial');

        $operationalSummary = $canViewOperational
            ? $this->getOperationalReportAction->handle(
                clinicId: $clinicId,
                userId: (int) $user->id,
                fromDate: $fromDate,
                toDate: $toDate,
            )
            : null;

        $financialSummary = $canViewFinancial
            ? $this->getFinancialReportAction->handle(
                clinicId: $clinicId,
                userId: (int) $user->id,
                fromDate: $fromDate,
                toDate: $toDate,
            )
            : null;

        $doctorPerformance = $canViewOperational
            ? $this->getDoctorPerformanceReportAction->handle(
                clinicId: $clinicId,
                fromDate: $fromDate,
                toDate: $toDate,
            )
            : null;

        $diagnosticsSummary = $canViewOperational
            ? $this->getClinicalDiagnosticsReportAction->handle(
                clinicId: $clinicId,
                fromDate: $fromDate,
                toDate: $toDate,
            )
            : null;

        $financialStatements = $canViewFinancial
            ? $this->getFinancialStatementsReportAction->handle(
                clinicId: $clinicId,
                fromDate: $fromDate,
                toDate: $toDate,
            )
            : null;

        return [
            'filters' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'can_view_operational' => $canViewOperational,
            'can_view_financial' => $canViewFinancial,
            'operational_summary' => $operationalSummary,
            'financial_summary' => $financialSummary,
            'doctor_performance' => $doctorPerformance,
            'diagnostics_summary' => $diagnosticsSummary,
            'financial_statements' => $financialStatements,
        ];
    }

    /**
     * @param  array{
     *     can_view_operational: bool,
     *     can_view_financial: bool
     * }  $payload
     */
    private function ensureReportExportAuthorized(array $payload): void
    {
        if ($payload['can_view_operational'] || $payload['can_view_financial']) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN, 'You do not have permission to export reports.');
    }

    /**
     * @param  resource  $output
     * @param  array<string, mixed>  $summary
     */
    private function writeOperationalSummaryRows($output, array $summary): void
    {
        fputcsv($output, ['Operational Summary']);
        fputcsv($output, ['Patients Total', (int) ($summary['patients_total'] ?? 0)]);
        fputcsv($output, ['Appointments Total', (int) ($summary['appointments']['total'] ?? 0)]);
        fputcsv($output, ['Queue Entries Total', (int) ($summary['queue_entries']['total'] ?? 0)]);
        fputcsv($output, ['Visits Total', (int) ($summary['visits']['total'] ?? 0)]);
    }

    /**
     * @param  resource  $output
     * @param  array<string, mixed>  $summary
     */
    private function writeFinancialSummaryRows($output, array $summary): void
    {
        fputcsv($output, ['Financial Summary']);
        fputcsv($output, ['Invoices Count', (int) ($summary['invoices']['count'] ?? 0)]);
        fputcsv($output, ['Total Invoiced', (float) ($summary['invoices']['total_amount'] ?? 0)]);
        fputcsv($output, ['Gross Collections', (float) ($summary['payments']['gross_collections'] ?? 0)]);
        fputcsv($output, ['Refund Amount', (float) ($summary['payments']['refund_amount'] ?? 0)]);
        fputcsv($output, ['Net Collections', (float) ($summary['payments']['net_collections'] ?? 0)]);
    }

    /**
     * @param  resource  $output
     * @param  array<string, mixed>  $summary
     */
    private function writeDoctorPerformanceRows($output, array $summary): void
    {
        fputcsv($output, ['Doctor Performance']);
        fputcsv($output, ['Doctors Count', (int) ($summary['doctors_count'] ?? 0)]);
        fputcsv($output, ['Total Visits', (int) ($summary['totals']['visits'] ?? 0)]);
        fputcsv($output, ['Total Unique Patients', (int) ($summary['totals']['unique_patients'] ?? 0)]);
        fputcsv($output, ['Total Lab Orders', (int) ($summary['totals']['lab_orders'] ?? 0)]);
        fputcsv($output, ['Total Radiology Orders', (int) ($summary['totals']['radiology_orders'] ?? 0)]);
        fputcsv($output, ['Total Revenue', (float) ($summary['totals']['revenue_amount'] ?? 0)]);
    }

    /**
     * @param  resource  $output
     * @param  array<string, mixed>  $summary
     */
    private function writeDiagnosticsRows($output, array $summary): void
    {
        fputcsv($output, ['Diagnostics Summary']);
        fputcsv($output, ['Lab Orders Total', (int) ($summary['lab']['orders_total'] ?? 0)]);
        fputcsv($output, ['Lab Resulted Total', (int) ($summary['lab']['resulted_total'] ?? 0)]);
        fputcsv($output, ['Lab Pending Total', (int) ($summary['lab']['pending_total'] ?? 0)]);
        fputcsv($output, ['Radiology Orders Total', (int) ($summary['radiology']['orders_total'] ?? 0)]);
        fputcsv($output, ['Radiology Reported Total', (int) ($summary['radiology']['reported_total'] ?? 0)]);
        fputcsv($output, ['Radiology Images Uploaded', (int) ($summary['radiology']['images_uploaded_total'] ?? 0)]);
        fputcsv($output, ['LIS Sent', (int) ($summary['integrations']['lis']['sent'] ?? 0)]);
        fputcsv($output, ['PACS Sent', (int) ($summary['integrations']['pacs']['sent'] ?? 0)]);
    }

    /**
     * @param  resource  $output
     * @param  array<string, mixed>  $summary
     */
    private function writeFinancialStatementsRows($output, array $summary): void
    {
        fputcsv($output, ['Financial Statements']);
        fputcsv($output, ['Revenue', (float) ($summary['income_statement']['revenue'] ?? 0)]);
        fputcsv($output, ['Operating Expenses', (float) ($summary['income_statement']['operating_expenses'] ?? 0)]);
        fputcsv($output, ['Payroll Expenses', (float) ($summary['income_statement']['payroll_expenses'] ?? 0)]);
        fputcsv($output, ['Net Income', (float) ($summary['income_statement']['net_income'] ?? 0)]);
        fputcsv($output, ['Assets', (float) ($summary['balance_sheet']['assets'] ?? 0)]);
        fputcsv($output, ['Liabilities', (float) ($summary['balance_sheet']['liabilities'] ?? 0)]);
        fputcsv($output, ['Equity', (float) ($summary['balance_sheet']['equity'] ?? 0)]);
        fputcsv($output, ['Net Cashflow', (float) ($summary['cash_flow']['net_cashflow'] ?? 0)]);
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
