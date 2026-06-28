<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\Cache\CacheService;
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
    public function __construct(private CacheService $cacheService) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = $this->resolveFilters($request);
        [$periodStart, $periodEnd] = $this->resolvePeriod($filters);

        $selectedClinicId = $this->nullableInteger($filters['clinic_id'] ?? null);
        $userClinicId = $this->getUserClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request) && $selectedClinicId === null;

        $clinicId = $selectedClinicId ?? $userClinicId;

        $clinic = Clinic::query()->find($clinicId);
        $clinicName = $clinic?->name ?? '-';

        $rows = $this->appointmentFinancialRows($clinicId, $clinicName, $filters, $periodStart, $periodEnd, $includeAllClinics);
        $summaries = $this->summaries($rows);

        $payload = [
            'financial_rows' => $rows->values()->all(),
            'summaries' => $summaries,
            'filters' => [
                ...$filters,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'clinics' => $this->clinicOptions(),
            'doctors' => $this->doctorsDropdown($clinicId, $includeAllClinics),
            'patients' => $this->patientsDropdown($clinicId, $includeAllClinics),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('financial/Index', $payload);
    }

    private function getUserClinicId(Request $request): ?int
    {
        $clinicId = $request->user()?->clinic_id;

        return $clinicId !== null ? (int) $clinicId : null;
    }

    private function canViewAllClinics(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('clinic_admin'));
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $this->getUserClinicId($request);

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return $clinicId;
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
            'clinic_id' => $this->nullableInteger($request->query('clinic_id')),
            'doctor_id' => $this->nullableInteger($request->query('doctor_id')),
            'patient_id' => $this->nullableInteger($request->query('patient_id')),
            'appointment_type' => $this->allowedNullableString($request->query('appointment_type'), ['first_visit', 'review']),
            'payment_method' => $this->allowedNullableString($request->query('payment_method'), ['cash', 'card', 'bank_transfer', 'insurance', 'online']),
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
    private function appointmentFinancialRows(int $clinicId, string $clinicName, array $filters, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeAllClinics = false): Collection
    {
        $query = Appointment::query()
            ->withoutTrashed()
            ->with([
                'clinic:id,name',
                'patient' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'first_name', 'last_name', 'file_number'),
                'doctor' => fn ($q) => $q->withoutGlobalScope('clinic')->select('id', 'clinic_id', 'name'),
            ])
            ->whereBetween('scheduled_for', [$periodStart, $periodEnd])
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW]);

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        if ($filters['doctor_id'] !== null) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if ($filters['patient_id'] !== null) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if ($filters['appointment_type'] !== null) {
            $query->where('appointment_type', $filters['appointment_type']);
        }

        $appointments = $query->orderBy('scheduled_for', 'desc')->get();

        $paymentData = $this->paymentDataForAppointments($appointments->pluck('id')->all());

        $rows = $appointments->map(function (Appointment $appointment) use ($clinicName, $paymentData): array {
            $appointmentId = $appointment->id;
            $paidAmount = (float) ($paymentData[$appointmentId]['paid'] ?? 0);
            $cost = (float) ($appointment->cost ?? 0);
            $remaining = max(0, $cost - $paidAmount);
            $lastPaymentMethod = $paymentData[$appointmentId]['last_method'] ?? null;

            return [
                'appointment_id' => $appointmentId,
                'clinic_name' => $appointment->clinic?->name ?? $clinicName,
                'patient_name' => trim(($appointment->patient?->first_name ?? '').' '.($appointment->patient?->last_name ?? '')),
                'file_number' => $appointment->patient?->file_number,
                'doctor_name' => $appointment->doctor?->name ?? '-',
                'appointment_type' => $appointment->appointment_type ?? 'first_visit',
                'cost' => $cost,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remaining,
                'payment_status' => $this->paymentStatus($cost, $paidAmount),
                'appointment_date' => $appointment->scheduled_for?->toDateString(),
                'payment_method' => $lastPaymentMethod,
            ];
        });

        $rows = $rows->filter(function (array $row) use ($filters): bool {
            if ($filters['status'] !== null && $row['payment_status'] !== $filters['status']) {
                return false;
            }

            if ($filters['payment_method'] !== null && $row['payment_method'] !== $filters['payment_method']) {
                return false;
            }

            return true;
        })->values();

        return $rows;
    }

    /**
     * @param  array<int>  $appointmentIds
     * @return array<int, array{paid: float, last_method: string|null}>
     */
    private function paymentDataForAppointments(array $appointmentIds): array
    {
        if (empty($appointmentIds)) {
            return [];
        }

        $invoicePayments = Invoice::query()
            ->withoutGlobalScope('clinic')
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
     * @return array<int, array{id: int, name: string}>
     */
    private function doctorsDropdown(int $clinicId, bool $includeAllClinics = false): array
    {
        $query = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('doctor_profiles.is_active', true)
            ->join('users', 'users.id', '=', 'doctor_profiles.user_id')
            ->select('users.id', 'users.name');

        if (! $includeAllClinics) {
            $query->where('doctor_profiles.clinic_id', $clinicId);
        }

        return $query
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, full_name: string}>
     */
    private function patientsDropdown(int $clinicId, bool $includeAllClinics = false): array
    {
        $query = Patient::query()
            ->withoutGlobalScope('clinic')
            ->withoutTrashed();

        if (! $includeAllClinics) {
            $query->where('clinic_id', $clinicId);
        }

        return $query
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"))
            ->orderBy('first_name')
            ->limit(500)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'full_name' => $row->full_name])
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
            ->map(fn (Clinic $clinic): array => [
                'id' => (int) $clinic->id,
                'name' => $clinic->name,
            ])
            ->all();
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
