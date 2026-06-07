<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class FinancialController extends Controller
{
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $filters = $this->resolveFilters($request);
        [$periodStart, $periodEnd] = $this->resolvePeriod($filters);

        $rows = $this->appointmentFinancialRows($clinicId, $filters, $periodStart, $periodEnd);
        $summaries = $this->summaries($rows);

        $payload = [
            'financial_rows' => $rows->values()->all(),
            'summaries' => $summaries,
            'departments' => $this->departmentOptions($clinicId),
            'filters' => [
                ...$filters,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('financial/Index', $payload);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'month' => $this->nullableString($request->query('month')) ?? now()->format('Y-m'),
            'date_from' => $this->nullableString($request->query('date_from')),
            'date_to' => $this->nullableString($request->query('date_to')),
            'status' => $this->allowedNullableString($request->query('status'), ['unpaid', 'partially_paid', 'paid']),
            'department_id' => $this->nullableInteger($request->query('department_id')),
            'doctor_id' => $this->nullableInteger($request->query('doctor_id')),
            'appointment_type' => $this->allowedNullableString($request->query('appointment_type'), ['first_visit', 'review']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(array $filters): array
    {
        if ($filters['date_from'] !== null && $filters['date_to'] !== null) {
            return [
                CarbonImmutable::parse($filters['date_from'])->startOfDay(),
                CarbonImmutable::parse($filters['date_to'])->endOfDay(),
            ];
        }

        $month = CarbonImmutable::createFromFormat('Y-m', $filters['month']) ?: CarbonImmutable::now();

        return [$month->startOfMonth(), $month->endOfMonth()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function appointmentFinancialRows(int $clinicId, array $filters, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Collection
    {
        $query = Appointment::query()
            ->forClinic($clinicId)
            ->withoutTrashed()
            ->with([
                'patient:id,clinic_id,first_name,last_name,file_number',
                'doctor:id,clinic_id,name',
                'doctor.doctorProfile:id,clinic_id,user_id,department_id',
                'doctor.doctorProfile.department:id,clinic_id,name',
            ])
            ->whereBetween('scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);

        if ($filters['department_id'] !== null) {
            $query->whereHas('doctor.doctorProfile', function ($q) use ($filters): void {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if ($filters['doctor_id'] !== null) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if ($filters['appointment_type'] !== null) {
            $query->where('appointment_type', $filters['appointment_type']);
        }

        $appointments = $query->orderBy('scheduled_for', 'desc')->get();

        $paymentData = $this->paymentDataForAppointments($clinicId, $appointments->pluck('id')->all());

        return $appointments->map(function (Appointment $appointment) use ($paymentData): array {
            $appointmentId = $appointment->id;
            $paidAmount = (float) ($paymentData[$appointmentId]['paid'] ?? 0);
            $cost = (float) ($appointment->cost ?? 0);
            $remaining = max(0, $cost - $paidAmount);
            $lastPaymentMethod = $paymentData[$appointmentId]['last_method'] ?? null;

            return [
                'appointment_id' => $appointmentId,
                'patient_name' => trim(($appointment->patient?->first_name ?? '').' '.($appointment->patient?->last_name ?? '')),
                'file_number' => $appointment->patient?->file_number,
                'doctor_name' => $appointment->doctor?->name ?? '-',
                'department' => $appointment->doctor?->doctorProfile?->department?->name ?? '-',
                'appointment_type' => $appointment->appointment_type ?? 'first_visit',
                'cost' => $cost,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'payment_status' => $this->paymentStatus($cost, $paidAmount),
                'appointment_date' => $appointment->scheduled_for?->toDateString(),
                'payment_method' => $lastPaymentMethod,
            ];
        })->filter(function (array $row) use ($filters): bool {
            if ($filters['status'] === null) {
                return true;
            }

            return $row['payment_status'] === $filters['status'];
        })->values();
    }

    /**
     * @param  array<int>  $appointmentIds
     * @return array<int, array{paid: float, last_method: string|null}>
     */
    private function paymentDataForAppointments(int $clinicId, array $appointmentIds): array
    {
        if (empty($appointmentIds)) {
            return [];
        }

        $invoicePayments = DB::table('invoices')
            ->forClinic($clinicId)
            ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.appointment_id', $appointmentIds)
            ->whereNotIn('payments.status', Payment::TERMINAL_STATUSES)
            ->select(
                'invoices.appointment_id',
                DB::raw('SUM(payments.amount - payments.refund_amount) as total_paid'),
                DB::raw('MAX(payments.method) as last_method'),
            )
            ->groupBy('invoices.appointment_id')
            ->get()
            ->keyBy('appointment_id');

        $result = [];

        foreach ($appointmentIds as $id) {
            $record = $invoicePayments->get($id);
            $result[$id] = [
                'paid' => $record ? (float) $record->total_paid : 0,
                'last_method' => $record?->last_method,
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    private function summaries(Collection $rows): array
    {
        $totalCost = (float) $rows->sum('cost');
        $totalPaid = (float) $rows->sum('paid_amount');
        $totalRemaining = (float) $rows->sum('remaining_amount');
        $paidCount = $rows->where('payment_status', 'paid')->count();
        $unpaidCount = $rows->where('payment_status', 'unpaid')->count();
        $partiallyPaidCount = $rows->where('payment_status', 'partially_paid')->count();

        return [
            'total_cost' => $totalCost,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
            'partially_paid_count' => $partiallyPaidCount,
        ];
    }

    private function paymentStatus(float $cost, float $paid): string
    {
        if ($cost <= 0) {
            return 'unpaid';
        }

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid >= $cost) {
            return 'paid';
        }

        return 'partially_paid';
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function departmentOptions(int $clinicId): array
    {
        return Department::query()
            ->forClinic($clinicId)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => ['id' => $department->id, 'name' => $department->name])
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedNullableString(mixed $value, array $allowed): ?string
    {
        $value = $this->nullableString($value);

        return $value !== null && in_array($value, $allowed, true) ? $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
