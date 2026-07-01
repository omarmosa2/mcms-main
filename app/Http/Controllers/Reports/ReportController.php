<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\GetClinicalDiagnosticsReportAction;
use App\Actions\Reports\GetDoctorPerformanceReportAction;
use App\Actions\Reports\GetFinancialReportAction;
use App\Actions\Reports\GetFinancialStatementsReportAction;
use App\Actions\Reports\GetOperationalReportAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorAppointmentEntitlement;
use App\Models\DoctorPayment;
use App\Models\DoctorProfile;
use App\Models\EmployeeMonthlySalary;
use App\Models\EmployeeSalaryPayment;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Payment;
use App\Support\MinimalPdfDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        if ($request->expectsJson()) {
            $payload = $this->buildPayload($request);

            return response()->json(['data' => $payload]);
        }

        return Inertia::render('reports/Index', $this->buildInertiaPayload($request));
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
     *     filters: array<string, mixed>,
     *     can_view_operational: bool,
     *     can_view_financial: bool,
     *     operational_summary: array<string, mixed>|null,
     *     financial_summary: array<string, mixed>|null,
     *     doctor_performance: array<string, mixed>|null,
     *     diagnostics_summary: array<string, mixed>|null,
     *     financial_statements: array<string, mixed>|null,
     *     report_data: array<string, mixed>,
     *     chart_data: array<string, mixed>,
     *     clinics: array<int, array{id: int, name: string}>,
     *     doctors: array<int, array{id: int, name: string}>
     * }
     */
    private function buildPayload(Request $request): array
    {
        $context = $this->resolveReportContext($request);

        $operationalSummary = $context['canViewOperational']
            ? $this->getOperationalReportAction->handle(
                clinicId: $context['clinicId'],
                userId: $context['userId'],
                fromDate: $context['fromDate'],
                toDate: $context['toDate'],
            )
            : null;

        $financialSummary = $context['canViewFinancial']
            ? $this->getFinancialReportAction->handle(
                clinicId: $context['clinicId'],
                userId: $context['userId'],
                fromDate: $context['fromDate'],
                toDate: $context['toDate'],
            )
            : null;

        $doctorPerformance = $context['canViewOperational']
            ? $this->getDoctorPerformanceReportAction->handle(
                clinicId: $context['clinicId'],
                fromDate: $context['fromDate'],
                toDate: $context['toDate'],
            )
            : null;

        $diagnosticsSummary = $context['canViewOperational']
            ? $this->getClinicalDiagnosticsReportAction->handle(
                clinicId: $context['clinicId'],
                fromDate: $context['fromDate'],
                toDate: $context['toDate'],
            )
            : null;

        $financialStatements = $context['canViewFinancial']
            ? $this->getFinancialStatementsReportAction->handle(
                clinicId: $context['clinicId'],
                fromDate: $context['fromDate'],
                toDate: $context['toDate'],
            )
            : null;

        return [
            'filters' => $this->filtersPayload($context),
            'can_view_operational' => $context['canViewOperational'],
            'can_view_financial' => $context['canViewFinancial'],
            'operational_summary' => $operationalSummary,
            'financial_summary' => $financialSummary,
            'doctor_performance' => $doctorPerformance,
            'diagnostics_summary' => $diagnosticsSummary,
            'financial_statements' => $financialStatements,
            'report_data' => $this->reportData($context),
            'chart_data' => $this->chartData($context),
            'clinics' => $this->clinicOptions(),
            'doctors' => $this->doctorOptions($context['clinicId']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInertiaPayload(Request $request): array
    {
        $context = $this->resolveReportContext($request);

        return [
            'filters' => $this->filtersPayload($context),
            'can_view_operational' => $context['canViewOperational'],
            'can_view_financial' => $context['canViewFinancial'],
            'clinics' => $this->clinicOptions(),
            'doctors' => $this->doctorOptions($context['clinicId']),
            'operational_summary' => $context['canViewOperational']
                ? Inertia::defer(fn (): array => $this->getOperationalReportAction->handle(
                    clinicId: $context['clinicId'],
                    userId: $context['userId'],
                    fromDate: $context['fromDate'],
                    toDate: $context['toDate'],
                ), 'reports')
                : null,
            'financial_summary' => $context['canViewFinancial']
                ? Inertia::defer(fn (): array => $this->getFinancialReportAction->handle(
                    clinicId: $context['clinicId'],
                    userId: $context['userId'],
                    fromDate: $context['fromDate'],
                    toDate: $context['toDate'],
                ), 'reports')
                : null,
            'doctor_performance' => $context['canViewOperational']
                ? Inertia::defer(fn (): array => $this->getDoctorPerformanceReportAction->handle(
                    clinicId: $context['clinicId'],
                    fromDate: $context['fromDate'],
                    toDate: $context['toDate'],
                ), 'reports')
                : null,
            'diagnostics_summary' => $context['canViewOperational']
                ? Inertia::defer(fn (): array => $this->getClinicalDiagnosticsReportAction->handle(
                    clinicId: $context['clinicId'],
                    fromDate: $context['fromDate'],
                    toDate: $context['toDate'],
                ), 'reports')
                : null,
            'financial_statements' => $context['canViewFinancial']
                ? Inertia::defer(fn (): array => $this->getFinancialStatementsReportAction->handle(
                    clinicId: $context['clinicId'],
                    fromDate: $context['fromDate'],
                    toDate: $context['toDate'],
                ), 'reports')
                : null,
            'report_data' => Inertia::defer(fn (): array => $this->reportData($context), 'reports'),
            'chart_data' => Inertia::defer(fn (): array => $this->chartData($context), 'reports'),
        ];
    }

    /**
     * @return array{
     *     clinicId: int,
     *     userId: int,
     *     month: ?string,
     *     fromDate: ?string,
     *     toDate: ?string,
     *     doctorId: ?int,
     *     reportType: ?string,
     *     canViewOperational: bool,
     *     canViewFinancial: bool
     * }
     */
    private function resolveReportContext(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'month' => ['nullable', 'date_format:Y-m'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'report_type' => ['nullable', 'string', 'in:overview,financial,payroll,clinics,doctors,appointments,patients,expenses,pharmacy'],
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN, 'Authentication is required.');
        }

        if (! $user->hasRole('super_admin') && ! $user->hasRole('admin') && ! $user->hasRole('clinic_admin')) {
            abort(Response::HTTP_FORBIDDEN, 'Reports are only available for admins.');
        }

        [$from, $to] = $this->resolvePeriod(
            $validated['month'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        return [
            'clinicId' => $this->resolveClinicId($request, isset($validated['clinic_id']) ? (int) $validated['clinic_id'] : null),
            'userId' => (int) $user->id,
            'month' => isset($validated['month']) ? (string) $validated['month'] : $from->format('Y-m'),
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
            'doctorId' => isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null,
            'reportType' => isset($validated['report_type']) ? (string) $validated['report_type'] : 'overview',
            'canViewOperational' => $user->hasPermission('reports.view'),
            'canViewFinancial' => $user->hasPermission('reports.financial') || $user->hasPermission('reports.view'),
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function resolvePeriod(?string $month, ?string $fromDate, ?string $toDate): array
    {
        if ($fromDate !== null || $toDate !== null) {
            $from = $fromDate !== null ? CarbonImmutable::parse($fromDate)->startOfDay() : CarbonImmutable::now()->startOfMonth();
            $to = $toDate !== null ? CarbonImmutable::parse($toDate)->endOfDay() : $from->endOfMonth();

            return $from->greaterThan($to) ? [$to->startOfDay(), $from->endOfDay()] : [$from, $to];
        }

        $base = $month !== null
            ? CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return [$base, $base->endOfMonth()];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function filtersPayload(array $context): array
    {
        return [
            'from' => $context['fromDate'],
            'to' => $context['toDate'],
            'month' => $context['month'],
            'clinic_id' => $context['clinicId'],
            'doctor_id' => $context['doctorId'],
            'report_type' => $context['reportType'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function reportData(array $context): array
    {
        $periodStart = CarbonImmutable::parse($context['fromDate'])->startOfDay();
        $periodEnd = CarbonImmutable::parse($context['toDate'])->endOfDay();
        $clinicId = (int) $context['clinicId'];
        $doctorId = $context['doctorId'];

        $appointments = $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctorId);
        $paymentsByAppointment = $this->paymentsByAppointment((clone $appointments)->pluck('appointments.id')->all());
        $appointmentRows = (clone $appointments)
            ->with([
                'clinic:id,name',
                'patient' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'first_name', 'last_name', 'file_number'),
                'doctor:id,name',
            ])
            ->orderByDesc('scheduled_for')
            ->limit(25)
            ->get()
            ->map(fn (Appointment $appointment): array => $this->appointmentRow($appointment, $paymentsByAppointment))
            ->all();

        $doctorRows = $this->doctorReportRows($clinicId, $periodStart, $periodEnd, $doctorId);
        $clinicRows = $this->clinicReportRows($clinicId, $periodStart, $periodEnd);
        $expenseRows = $this->expenseReportRows($clinicId, $periodStart, $periodEnd);
        $patientRows = $this->patientReportRows($clinicId, $periodStart, $periodEnd);
        $payrollRows = $this->payrollRows($clinicId, $context['month']);

        $totalIncome = (float) (clone $appointments)
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
            ->sum('cost');
        $totalCollected = (float) array_sum(array_column($paymentsByAppointment, 'paid'));
        $totalExpenses = (float) Expense::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('status', Expense::STATUS_PAID)
            ->whereBetween('expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('amount');

        $doctorDue = (float) collect($doctorRows)->sum('due_amount');
        $doctorPaid = (float) DoctorPayment::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->whereBetween('paid_at', [$periodStart, $periodEnd])
            ->sum('amount');
        $employeeDue = (float) EmployeeMonthlySalary::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('salary_month', $context['month'])
            ->sum('due_amount');
        $employeePaid = (float) EmployeeSalaryPayment::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->whereBetween('payment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('amount');

        return [
            'overview' => [
                'patients_total' => Patient::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)->count(),
                'new_patients' => Patient::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
                'appointments_total' => (clone $appointments)->count(),
                'today_appointments' => Appointment::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)->whereDate('scheduled_for', CarbonImmutable::now()->toDateString())->count(),
                'total_income' => $totalIncome,
                'total_collected' => $totalCollected,
                'total_remaining' => max(0, $totalIncome - $totalCollected),
                'total_expenses' => $totalExpenses,
                'doctor_due' => $doctorDue,
                'employee_salaries' => $employeeDue,
                'net_profit' => $totalIncome - $totalExpenses - $doctorDue - $employeeDue,
                'top_clinic' => $clinicRows[0]['clinic_name'] ?? '-',
                'top_doctor' => $doctorRows[0]['doctor_name'] ?? '-',
            ],
            'financial' => [
                'total_income' => $totalIncome,
                'total_collected' => $totalCollected,
                'total_remaining' => max(0, $totalIncome - $totalCollected),
                'expenses' => $totalExpenses,
                'net_before_payroll' => $totalIncome - $totalExpenses,
                'net_after_payroll' => $totalIncome - $totalExpenses - $doctorDue - $employeeDue,
                'net_liquidity' => $totalCollected - $totalExpenses - $doctorPaid - $employeePaid,
            ],
            'payroll' => [
                'doctor_due' => $doctorDue,
                'percentage_due' => (float) collect($doctorRows)->where('payment_type', DoctorProfile::COMPENSATION_PERCENTAGE)->sum('due_amount'),
                'weekly_due' => (float) collect($doctorRows)->where('payment_type', DoctorProfile::COMPENSATION_WEEKLY_FIXED)->sum('due_amount'),
                'monthly_due' => (float) collect($doctorRows)->where('payment_type', DoctorProfile::COMPENSATION_MONTHLY_FIXED)->sum('due_amount'),
                'employee_due' => $employeeDue,
                'paid' => $doctorPaid + $employeePaid,
                'remaining' => max(0, $doctorDue + $employeeDue - $doctorPaid - $employeePaid),
                'rows' => $payrollRows,
            ],
            'clinics' => ['active_count' => Clinic::query()->clinical()->where('is_active', true)->count(), 'rows' => $clinicRows],
            'doctors' => ['active_count' => DoctorProfile::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinicId)->where('is_active', true)->count(), 'rows' => $doctorRows],
            'appointments' => ['rows' => $appointmentRows],
            'patients' => ['rows' => $patientRows],
            'expenses' => ['total' => $totalExpenses, 'rows' => $expenseRows],
            'pharmacy' => $this->pharmacySummary(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function chartData(array $context): array
    {
        $periodStart = CarbonImmutable::parse($context['fromDate'])->startOfDay();
        $periodEnd = CarbonImmutable::parse($context['toDate'])->endOfDay();
        $clinicId = (int) $context['clinicId'];
        $doctorId = $context['doctorId'];

        return [
            'daily_income' => $this->dailyIncomeData($clinicId, $periodStart, $periodEnd, $doctorId),
            'income_by_clinic' => $this->incomeByClinicData($clinicId, $periodStart, $periodEnd),
            'income_by_doctor' => $this->incomeByDoctorData($clinicId, $periodStart, $periodEnd, $doctorId),
            'payment_status' => $this->paymentStatusData($clinicId, $periodStart, $periodEnd, $doctorId),
            'appointments_by_day' => $this->appointmentsByDayData($clinicId, $periodStart, $periodEnd, $doctorId),
            'appointments_by_status' => $this->appointmentsByStatusData($clinicId, $periodStart, $periodEnd, $doctorId),
            'expenses_by_category' => $this->expensesByCategoryData($clinicId, $periodStart, $periodEnd),
            'monthly_profit' => $this->monthlyProfitData($clinicId, $periodStart),
        ];
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointmentsQuery(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorId = null): Builder
    {
        return Appointment::query()
            ->withoutGlobalScope('clinic')
            ->where('appointments.clinic_id', $clinicId)
            ->when($doctorId !== null, fn (Builder $query) => $query->where('appointments.doctor_id', $doctorId))
            ->whereBetween('appointments.scheduled_for', [$periodStart, $periodEnd]);
    }

    /**
     * @param  array<int, int|string>  $appointmentIds
     * @return array<int, array{paid: float, last_method: ?string}>
     */
    private function paymentsByAppointment(array $appointmentIds): array
    {
        if ($appointmentIds === []) {
            return [];
        }

        return Payment::query()
            ->withoutGlobalScope('clinic')
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.appointment_id', $appointmentIds)
            ->whereNotIn('payments.status', Payment::TERMINAL_STATUSES)
            ->selectRaw('invoices.appointment_id, SUM(payments.amount - COALESCE(payments.refund_amount, 0)) as paid, MAX(payments.method) as last_method')
            ->groupBy('invoices.appointment_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->appointment_id => [
                    'paid' => (float) $row->paid,
                    'last_method' => $row->last_method !== null ? (string) $row->last_method : null,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{paid: float, last_method: ?string}>  $paymentsByAppointment
     * @return array<string, mixed>
     */
    private function appointmentRow(Appointment $appointment, array $paymentsByAppointment): array
    {
        $paid = (float) ($paymentsByAppointment[(int) $appointment->id]['paid'] ?? 0);
        $cost = (float) $appointment->cost;

        return [
            'appointment_id' => (int) $appointment->id,
            'date' => $appointment->scheduled_for?->format('Y-m-d H:i'),
            'patient_name' => trim(($appointment->patient?->first_name ?? '').' '.($appointment->patient?->last_name ?? '')) ?: '-',
            'file_number' => $appointment->patient?->file_number,
            'clinic_name' => $appointment->clinic?->name ?? '-',
            'doctor_name' => $appointment->doctor?->name ?? '-',
            'appointment_type' => $appointment->appointment_type ?? $appointment->type ?? '-',
            'status' => $appointment->status,
            'cost' => $cost,
            'paid_amount' => $paid,
            'remaining_amount' => max(0, $cost - $paid),
            'payment_status' => $this->paymentStatus($cost, $paid),
        ];
    }

    private function paymentStatus(float $cost, float $paid): string
    {
        if ($cost <= 0.0) {
            return 'free';
        }

        if ($paid <= 0.0) {
            return 'unpaid';
        }

        return $paid + 0.001 >= $cost ? 'paid' : 'partially_paid';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function doctorReportRows(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorUserId = null): array
    {
        return DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->with(['clinic:id,name', 'user:id,name'])
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->when($doctorUserId !== null, fn (Builder $query) => $query->where('user_id', $doctorUserId))
            ->get()
            ->map(function (DoctorProfile $doctor) use ($clinicId, $periodStart, $periodEnd): array {
                $appointmentQuery = $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctor->user_id !== null ? (int) $doctor->user_id : null);
                $revenueQuery = (clone $appointmentQuery)->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);
                $due = (float) DoctorAppointmentEntitlement::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->where('doctor_profile_id', $doctor->id)
                    ->whereBetween('appointment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->where('status', '!=', DoctorAppointmentEntitlement::STATUS_CANCELLED)
                    ->sum('entitlement_amount');
                $paid = (float) DoctorPayment::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('paid_at', [$periodStart, $periodEnd])
                    ->sum('amount');

                return [
                    'doctor_id' => (int) $doctor->id,
                    'doctor_user_id' => $doctor->user_id !== null ? (int) $doctor->user_id : null,
                    'doctor_name' => $doctor->full_name ?: ($doctor->user?->name ?? '-'),
                    'clinic_name' => $doctor->clinic?->name ?? '-',
                    'payment_type' => $doctor->compensation_type,
                    'appointments_count' => (clone $appointmentQuery)->count(),
                    'completed_count' => (clone $appointmentQuery)->where('status', Appointment::STATUS_COMPLETED)->count(),
                    'revenue_amount' => (float) $revenueQuery->sum('cost'),
                    'due_amount' => $due,
                    'paid_amount' => $paid,
                    'remaining_amount' => max(0, $due - $paid),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['revenue_amount'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clinicReportRows(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Clinic::query()
            ->clinical()
            ->where('id', $clinicId)
            ->get()
            ->map(function (Clinic $clinic) use ($periodStart, $periodEnd): array {
                $appointments = $this->appointmentsQuery((int) $clinic->id, $periodStart, $periodEnd);

                return [
                    'clinic_id' => (int) $clinic->id,
                    'clinic_name' => $clinic->name,
                    'appointments_count' => (clone $appointments)->count(),
                    'patients_count' => Patient::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinic->id)->count(),
                    'doctors_count' => DoctorProfile::query()->withoutGlobalScope('clinic')->where('clinic_id', $clinic->id)->where('is_active', true)->count(),
                    'income_amount' => (float) (clone $appointments)
                        ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
                        ->sum('cost'),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expenseReportRows(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Expense::query()
            ->withoutGlobalScope('clinic')
            ->with(['clinic:id,name', 'category:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereBetween('expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderByDesc('expense_date')
            ->limit(25)
            ->get()
            ->map(fn (Expense $expense): array => [
                'expense_id' => (int) $expense->id,
                'date' => $expense->expense_date?->toDateString(),
                'description' => $expense->description,
                'category_name' => $expense->category?->name ?? 'بدون تصنيف',
                'clinic_name' => $expense->clinic?->name ?? '-',
                'status' => $expense->status,
                'payment_method' => $expense->payment_method ?? '-',
                'amount' => (float) $expense->amount,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function patientReportRows(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Patient::query()
            ->withoutGlobalScope('clinic')
            ->withCount(['appointments' => fn (Builder $query) => $query->whereBetween('scheduled_for', [$periodStart, $periodEnd])])
            ->where('clinic_id', $clinicId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (Patient $patient): array => [
                'patient_id' => (int) $patient->id,
                'patient_name' => trim(($patient->first_name ?? '').' '.($patient->last_name ?? '')) ?: '-',
                'file_number' => $patient->file_number,
                'phone' => $patient->phone,
                'created_at' => $patient->created_at?->format('Y-m-d'),
                'appointments_count' => (int) $patient->appointments_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payrollRows(int $clinicId, ?string $month): array
    {
        $employeeRows = EmployeeMonthlySalary::query()
            ->withoutGlobalScope('clinic')
            ->with('employee:id,full_name,job_title,employee_type')
            ->where('clinic_id', $clinicId)
            ->when($month !== null, fn (Builder $query) => $query->where('salary_month', $month))
            ->orderByDesc('salary_month')
            ->limit(25)
            ->get()
            ->map(fn (EmployeeMonthlySalary $salary): array => [
                'type' => 'employee',
                'name' => $salary->employee?->full_name ?? '-',
                'role' => $salary->employee?->job_title ?? $salary->employee?->employee_type ?? '-',
                'period' => $salary->salary_month,
                'due_amount' => (float) $salary->due_amount,
                'paid_amount' => (float) $salary->paid_amount,
                'remaining_amount' => (float) $salary->remaining_amount,
                'status' => $salary->status,
            ]);

        $doctorRows = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->limit(25)
            ->get()
            ->map(function (DoctorProfile $doctor) use ($clinicId, $month): array {
                $periodStart = $month !== null ? CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth() : CarbonImmutable::now()->startOfMonth();
                $periodEnd = $periodStart->endOfMonth();
                $due = (float) DoctorAppointmentEntitlement::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->where('doctor_profile_id', $doctor->id)
                    ->whereBetween('appointment_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->where('status', '!=', DoctorAppointmentEntitlement::STATUS_CANCELLED)
                    ->sum('entitlement_amount');
                $paid = (float) DoctorPayment::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->where('doctor_id', $doctor->id)
                    ->whereBetween('paid_at', [$periodStart, $periodEnd])
                    ->sum('amount');

                return [
                    'type' => 'doctor',
                    'name' => $doctor->full_name,
                    'role' => $doctor->compensation_type,
                    'period' => $periodStart->format('Y-m'),
                    'due_amount' => $due,
                    'paid_amount' => $paid,
                    'remaining_amount' => max(0, $due - $paid),
                    'status' => $paid + 0.001 >= $due && $due > 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
                ];
            });

        return $doctorRows->concat($employeeRows)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pharmacySummary(): array
    {
        if (! Schema::hasTable('pharmacy_drugs')) {
            return [
                'enabled' => false,
                'drugs_count' => 0,
                'dispenses_count' => 0,
                'low_stock_count' => 0,
            ];
        }

        return [
            'enabled' => true,
            'drugs_count' => DB::table('pharmacy_drugs')->count(),
            'dispenses_count' => Schema::hasTable('pharmacy_dispenses') ? DB::table('pharmacy_dispenses')->count() : 0,
            'low_stock_count' => Schema::hasColumn('pharmacy_drugs', 'current_stock') && Schema::hasColumn('pharmacy_drugs', 'min_stock_level')
                ? DB::table('pharmacy_drugs')->whereColumn('current_stock', '<=', 'min_stock_level')->count()
                : 0,
        ];
    }

    /**
     * @return array<int, array{date: string, amount: float}>
     */
    private function dailyIncomeData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorId): array
    {
        return $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctorId)
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
            ->selectRaw('DATE(scheduled_for) as date, SUM(cost) as amount')
            ->groupByRaw('DATE(scheduled_for)')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => ['date' => (string) $row->date, 'amount' => (float) $row->amount])
            ->all();
    }

    /**
     * @return array<int, array{clinic_name: string, amount: float}>
     */
    private function incomeByClinicData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Appointment::query()
            ->withoutGlobalScope('clinic')
            ->join('clinics', 'appointments.clinic_id', '=', 'clinics.id')
            ->where('appointments.clinic_id', $clinicId)
            ->whereBetween('appointments.scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
            ->selectRaw('clinics.name as clinic_name, SUM(appointments.cost) as amount')
            ->groupBy('clinics.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn (object $row): array => ['clinic_name' => (string) $row->clinic_name, 'amount' => (float) $row->amount])
            ->all();
    }

    /**
     * @return array<int, array{doctor_name: string, amount: float}>
     */
    private function incomeByDoctorData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorId): array
    {
        return $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctorId)
            ->leftJoin('users', 'appointments.doctor_id', '=', 'users.id')
            ->whereNotIn('appointments.status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
            ->selectRaw("COALESCE(users.name, '-') as doctor_name, SUM(appointments.cost) as amount")
            ->groupBy('users.name')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => ['doctor_name' => (string) $row->doctor_name, 'amount' => (float) $row->amount])
            ->all();
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function paymentStatusData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorId): array
    {
        $appointments = $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctorId)->get(['id', 'cost']);
        $paymentsByAppointment = $this->paymentsByAppointment($appointments->pluck('id')->all());

        return $appointments
            ->map(fn (Appointment $appointment): string => $this->paymentStatus((float) $appointment->cost, (float) ($paymentsByAppointment[(int) $appointment->id]['paid'] ?? 0)))
            ->countBy()
            ->map(fn (int $count, string $status): array => ['status' => $status, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private function appointmentsByDayData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorId): array
    {
        return $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctorId)
            ->selectRaw('DATE(scheduled_for) as date, COUNT(*) as count')
            ->groupByRaw('DATE(scheduled_for)')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => ['date' => (string) $row->date, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function appointmentsByStatusData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $doctorId): array
    {
        return $this->appointmentsQuery($clinicId, $periodStart, $periodEnd, $doctorId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => ['status' => (string) $row->status, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{category_name: string, amount: float}>
     */
    private function expensesByCategoryData(int $clinicId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Expense::query()
            ->withoutGlobalScope('clinic')
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->where('expenses.clinic_id', $clinicId)
            ->where('expenses.status', Expense::STATUS_PAID)
            ->whereBetween('expenses.expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw("COALESCE(expense_categories.name, 'بدون تصنيف') as category_name, SUM(expenses.amount) as amount")
            ->groupBy('expense_categories.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn (object $row): array => ['category_name' => (string) $row->category_name, 'amount' => (float) $row->amount])
            ->all();
    }

    /**
     * @return array<int, array{month: string, income: float, expenses: float, payroll: float, profit: float}>
     */
    private function monthlyProfitData(int $clinicId, CarbonImmutable $periodStart): array
    {
        $start = $periodStart->subMonths(5)->startOfMonth();

        return collect(range(0, 5))
            ->map(function (int $offset) use ($clinicId, $start): array {
                $monthStart = $start->addMonths($offset)->startOfMonth();
                $monthEnd = $monthStart->endOfMonth();
                $month = $monthStart->format('Y-m');
                $income = (float) $this->appointmentsQuery($clinicId, $monthStart, $monthEnd)
                    ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
                    ->sum('cost');
                $expenses = (float) Expense::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->where('status', Expense::STATUS_PAID)
                    ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('amount');
                $payroll = (float) DoctorPayment::query()
                    ->withoutGlobalScope('clinic')
                    ->where('clinic_id', $clinicId)
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])
                    ->sum('amount')
                    + (float) EmployeeSalaryPayment::query()
                        ->withoutGlobalScope('clinic')
                        ->where('clinic_id', $clinicId)
                        ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->sum('amount');

                return [
                    'month' => $month,
                    'income' => $income,
                    'expenses' => $expenses,
                    'payroll' => $payroll,
                    'profit' => $income - $expenses - $payroll,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function clinicOptions(): array
    {
        return Clinic::query()
            ->clinical()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Clinic $clinic): array => ['id' => (int) $clinic->id, 'name' => $clinic->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function doctorOptions(int $clinicId): array
    {
        return DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->with('user:id,name')
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->orderBy('full_name')
            ->get()
            ->map(fn (DoctorProfile $doctor): array => [
                'id' => (int) $doctor->user_id,
                'name' => $doctor->full_name ?: ($doctor->user?->name ?? '-'),
            ])
            ->all();
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

    private function resolveClinicId(Request $request, ?int $selectedClinicId = null): int
    {
        if ($selectedClinicId !== null) {
            return $selectedClinicId;
        }

        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            $clinicId = Clinic::query()->clinical()->where('is_active', true)->value('id');
        }

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
