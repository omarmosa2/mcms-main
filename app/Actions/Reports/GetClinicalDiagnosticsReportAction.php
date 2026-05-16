<?php

namespace App\Actions\Reports;

use App\Actions\BaseAction;
use App\Models\ExternalIntegration;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\RadiologyImage;
use App\Models\RadiologyOrder;
use App\Models\RadiologyReport;
use Carbon\CarbonImmutable;

class GetClinicalDiagnosticsReportAction extends BaseAction
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     lab: array<string, mixed>,
     *     radiology: array<string, mixed>,
     *     integrations: array<string, mixed>
     * }
     */
    public function handle(int $clinicId, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $to] = $this->resolvePeriod($fromDate, $toDate);

        $labOrdersInPeriod = LabOrder::query()
            ->forClinic($clinicId)
            ->whereBetween('ordered_at', [$from, $to]);

        $radiologyOrdersInPeriod = RadiologyOrder::query()
            ->forClinic($clinicId)
            ->whereBetween('ordered_at', [$from, $to]);

        $labResultsInPeriod = LabResult::query()
            ->forClinic($clinicId)
            ->whereBetween('resulted_at', [$from, $to]);

        $radiologyReportsInPeriod = RadiologyReport::query()
            ->forClinic($clinicId)
            ->whereBetween('reported_at', [$from, $to]);

        $labTurnaroundRows = LabOrder::query()
            ->where('lab_orders.clinic_id', $clinicId)
            ->join('lab_results', 'lab_results.lab_order_id', '=', 'lab_orders.id')
            ->where('lab_results.clinic_id', $clinicId)
            ->whereBetween('lab_orders.ordered_at', [$from, $to])
            ->get(['lab_orders.ordered_at', 'lab_results.resulted_at']);

        $avgLabTurnaround = null;

        if ($labTurnaroundRows->isNotEmpty()) {
            $totalMinutes = $labTurnaroundRows->sum(function ($row): int {
                if ($row->ordered_at === null || $row->resulted_at === null) {
                    return 0;
                }

                return max(0, CarbonImmutable::parse((string) $row->ordered_at)
                    ->diffInMinutes(CarbonImmutable::parse((string) $row->resulted_at), false));
            });

            $avgLabTurnaround = $totalMinutes / $labTurnaroundRows->count();
        }

        $topLabTests = (clone $labOrdersInPeriod)
            ->selectRaw('test_name, COUNT(*) as total')
            ->groupBy('test_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'test_name' => (string) $row->test_name,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $topRadiologyStudies = (clone $radiologyOrdersInPeriod)
            ->selectRaw('study_name, COUNT(*) as total')
            ->groupBy('study_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'study_name' => (string) $row->study_name,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $lisIntegrations = ExternalIntegration::query()
            ->forClinic($clinicId)
            ->where('integration_type', ExternalIntegration::TYPE_LIS_HL7)
            ->whereBetween('created_at', [$from, $to]);

        $pacsIntegrations = ExternalIntegration::query()
            ->forClinic($clinicId)
            ->where('integration_type', ExternalIntegration::TYPE_PACS)
            ->whereBetween('created_at', [$from, $to]);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'lab' => [
                'orders_total' => (clone $labOrdersInPeriod)->count(),
                'resulted_total' => (clone $labOrdersInPeriod)->where('status', LabOrder::STATUS_RESULTED)->count(),
                'pending_total' => (clone $labOrdersInPeriod)->whereIn('status', [
                    LabOrder::STATUS_ORDERED,
                    LabOrder::STATUS_SAMPLE_COLLECTED,
                ])->count(),
                'results_total' => (clone $labResultsInPeriod)->count(),
                'average_turnaround_minutes' => $avgLabTurnaround !== null ? round((float) $avgLabTurnaround, 2) : null,
                'top_tests' => $topLabTests,
            ],
            'radiology' => [
                'orders_total' => (clone $radiologyOrdersInPeriod)->count(),
                'reported_total' => (clone $radiologyOrdersInPeriod)->where('status', RadiologyOrder::STATUS_REPORTED)->count(),
                'completed_total' => (clone $radiologyOrdersInPeriod)->where('status', RadiologyOrder::STATUS_COMPLETED)->count(),
                'reports_total' => (clone $radiologyReportsInPeriod)->count(),
                'images_uploaded_total' => RadiologyImage::query()
                    ->forClinic($clinicId)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'top_studies' => $topRadiologyStudies,
            ],
            'integrations' => [
                'lis' => [
                    'queued' => (clone $lisIntegrations)->where('status', ExternalIntegration::STATUS_QUEUED)->count(),
                    'sent' => (clone $lisIntegrations)->where('status', ExternalIntegration::STATUS_SENT)->count(),
                    'failed' => (clone $lisIntegrations)->where('status', ExternalIntegration::STATUS_FAILED)->count(),
                ],
                'pacs' => [
                    'queued' => (clone $pacsIntegrations)->where('status', ExternalIntegration::STATUS_QUEUED)->count(),
                    'sent' => (clone $pacsIntegrations)->where('status', ExternalIntegration::STATUS_SENT)->count(),
                    'failed' => (clone $pacsIntegrations)->where('status', ExternalIntegration::STATUS_FAILED)->count(),
                ],
            ],
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function resolvePeriod(?string $fromDate, ?string $toDate): array
    {
        $from = $fromDate !== null
            ? CarbonImmutable::parse($fromDate)->startOfDay()
            : CarbonImmutable::now()->startOfMonth();

        $to = $toDate !== null
            ? CarbonImmutable::parse($toDate)->endOfDay()
            : CarbonImmutable::now()->endOfMonth();

        if ($from->greaterThan($to)) {
            return [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }
}
