<?php

namespace App\Actions\Reports;

use App\Actions\BaseAction;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\RadiologyOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetDoctorPerformanceReportAction extends BaseAction
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     doctors_count: int,
     *     totals: array{unique_patients: int, lab_orders: int, radiology_orders: int, revenue_amount: float},
     *     doctors: array<int, array<string, mixed>>
     * }
     */
    public function handle(int $clinicId, ?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $to] = $this->resolvePeriod($fromDate, $toDate);

        $doctors = User::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', function ($query) use ($clinicId): void {
                $query
                    ->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $labOrderAggregates = LabOrder::query()
            ->forClinic($clinicId)
            ->whereBetween('ordered_at', [$from, $to])
            ->whereNotNull('ordered_by')
            ->selectRaw('ordered_by as doctor_id, COUNT(*) as lab_orders_count')
            ->groupBy('ordered_by')
            ->pluck('lab_orders_count', 'doctor_id');

        $radiologyOrderAggregates = RadiologyOrder::query()
            ->forClinic($clinicId)
            ->whereBetween('ordered_at', [$from, $to])
            ->whereNotNull('ordered_by')
            ->selectRaw('ordered_by as doctor_id, COUNT(*) as radiology_orders_count')
            ->groupBy('ordered_by')
            ->pluck('radiology_orders_count', 'doctor_id');

        $revenueAggregates = Invoice::query()
            ->where('invoices.clinic_id', $clinicId)
            ->whereNotNull('issued_by')
            ->whereBetween('issued_at', [$from, $to])
            ->selectRaw('issued_by as doctor_id, COALESCE(SUM(total_amount), 0) as revenue_amount')
            ->groupBy('issued_by')
            ->pluck('revenue_amount', 'doctor_id');

        $doctorRows = $doctors->map(function (User $doctor) use ($labOrderAggregates, $radiologyOrderAggregates, $revenueAggregates): array {
            return [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'lab_orders_count' => (int) ($labOrderAggregates->get($doctor->id) ?? 0),
                'radiology_orders_count' => (int) ($radiologyOrderAggregates->get($doctor->id) ?? 0),
                'revenue_amount' => round((float) ($revenueAggregates->get($doctor->id) ?? 0), 2),
            ];
        })->sortByDesc('revenue_amount')->values();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'doctors_count' => $doctors->count(),
            'totals' => [
                'unique_patients' => $this->countUniquePatientsAcrossDoctors($doctorRows),
                'lab_orders' => (int) $doctorRows->sum('lab_orders_count'),
                'radiology_orders' => (int) $doctorRows->sum('radiology_orders_count'),
                'revenue_amount' => round((float) $doctorRows->sum('revenue_amount'), 2),
            ],
            'doctors' => $doctorRows->all(),
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

    /**
     * @param  Collection<int, array<string, mixed>>  $doctorRows
     */
    private function countUniquePatientsAcrossDoctors(Collection $doctorRows): int
    {
        return (int) $doctorRows
            ->sum(fn (array $row): int => (int) $row['unique_patients']);
    }
}
