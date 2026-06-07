<?php

namespace App\Actions\Reports;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Patient;
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
            'snapshot' => [
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
}
