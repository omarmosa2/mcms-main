<?php

namespace App\Actions\Reports;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class GetOperationalReportAction extends BaseAction
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

        $appointmentsInPeriod = Appointment::query()
            ->forClinic($clinicId)
            ->whereBetween('scheduled_for', [$from, $to]);

        $queueEntriesInPeriod = QueueEntry::query()
            ->forClinic($clinicId)
            ->whereBetween('queue_date', [$from->toDateString(), $to->toDateString()]);

        $visitsInPeriod = Visit::query()
            ->forClinic($clinicId)
            ->whereBetween('started_at', [$from, $to]);

        $today = CarbonImmutable::now()->toDateString();

        $report = [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'patients_total' => Patient::query()
                ->forClinic($clinicId)
                ->count(),
            'appointments' => [
                'total' => (clone $appointmentsInPeriod)->count(),
                'by_status' => $this->countAppointmentsByStatus($appointmentsInPeriod),
            ],
            'queue_entries' => [
                'total' => (clone $queueEntriesInPeriod)->count(),
                'by_status' => $this->countQueueEntriesByStatus($queueEntriesInPeriod),
            ],
            'visits' => [
                'total' => (clone $visitsInPeriod)->count(),
                'by_status' => $this->countVisitsByStatus($visitsInPeriod),
            ],
            'snapshot' => [
                'waiting_queue_today' => QueueEntry::query()
                    ->forClinic($clinicId)
                    ->whereDate('queue_date', $today)
                    ->where('status', QueueEntry::STATUS_WAITING)
                    ->count(),
                'active_visits' => Visit::query()
                    ->forClinic($clinicId)
                    ->whereIn('status', [
                        Visit::STATUS_STARTED,
                        Visit::STATUS_IN_PROGRESS,
                    ])
                    ->count(),
                'arrived_appointments_today' => Appointment::query()
                    ->forClinic($clinicId)
                    ->whereDate('scheduled_for', $today)
                    ->where('status', Appointment::STATUS_ARRIVED)
                    ->count(),
            ],
        ];

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'reports.view',
            metadata: [
                'scope' => 'operational',
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
     * @param  Builder<Appointment>  $query
     * @return array<string, int>
     */
    private function countAppointmentsByStatus($query): array
    {
        $statuses = [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_ARRIVED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELED,
            Appointment::STATUS_NO_SHOW,
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

    /**
     * @param  Builder<QueueEntry>  $query
     * @return array<string, int>
     */
    private function countQueueEntriesByStatus($query): array
    {
        $statuses = [
            QueueEntry::STATUS_WAITING,
            QueueEntry::STATUS_CALLED,
            QueueEntry::STATUS_IN_SERVICE,
            QueueEntry::STATUS_COMPLETED,
            QueueEntry::STATUS_SKIPPED,
            QueueEntry::STATUS_CANCELED,
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

    /**
     * @param  Builder<Visit>  $query
     * @return array<string, int>
     */
    private function countVisitsByStatus($query): array
    {
        $statuses = [
            Visit::STATUS_STARTED,
            Visit::STATUS_IN_PROGRESS,
            Visit::STATUS_COMPLETED,
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
