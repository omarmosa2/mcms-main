<?php

namespace App\Actions\Reports;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class GetFinancialReportAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        int $clinicId,
        int $userId,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        [$from, $to] = $this->resolvePeriod($fromDate, $toDate);

        $invoicesInPeriod = Invoice::query()
            ->forClinic($clinicId)
            ->whereBetween('created_at', [$from, $to]);

        $paymentsInPeriod = Payment::query()
            ->forClinic($clinicId)
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('status', [Payment::STATUS_RECORDED, Payment::STATUS_REFUNDED]);

        $refundsInPeriod = Payment::query()
            ->forClinic($clinicId)
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$from, $to]);

        $grossCollections = (float) (clone $paymentsInPeriod)->sum('amount');
        $refundAmount = (float) (clone $refundsInPeriod)->sum('refund_amount');

        $report = [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'invoices' => [
                'count' => (clone $invoicesInPeriod)->count(),
                'total_amount' => (float) (clone $invoicesInPeriod)->sum('total_amount'),
                'issued_amount' => (float) (clone $invoicesInPeriod)
                    ->whereIn('status', [
                        Invoice::STATUS_ISSUED,
                        Invoice::STATUS_PARTIALLY_PAID,
                        Invoice::STATUS_PAID,
                    ])
                    ->sum('total_amount'),
                'outstanding_balance' => (float) Invoice::query()
                    ->forClinic($clinicId)
                    ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
                    ->sum('balance_amount'),
                'overdue_count' => Invoice::query()
                    ->forClinic($clinicId)
                    ->whereDate('due_at', '<', CarbonImmutable::now()->toDateString())
                    ->where('balance_amount', '>', 0)
                    ->count(),
                'by_status' => $this->countInvoicesByStatus($invoicesInPeriod),
            ],
            'payments' => [
                'count' => (clone $paymentsInPeriod)->count(),
                'gross_collections' => $grossCollections,
                'refund_amount' => $refundAmount,
                'net_collections' => round($grossCollections - $refundAmount, 2),
            ],
        ];

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'reports.financial',
            metadata: [
                'scope' => 'financial',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        );

        return $report;
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

    /**
     * @param  Builder<Invoice>  $query
     * @return array<string, int>
     */
    private function countInvoicesByStatus($query): array
    {
        $statuses = [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ];

        $counts = array_fill_keys($statuses, 0);

        $results = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        foreach ($results as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->count;
            }
        }

        return $counts;
    }
}
