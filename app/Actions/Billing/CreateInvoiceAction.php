<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): Invoice
    {
        $patient = $this->resolvePatient($clinicId, (int) $payload['patient_id']);
        $appointment = $this->resolveAppointmentIfProvided($clinicId, $payload['appointment_id'] ?? null);

        if ($appointment !== null && (int) $appointment->patient_id !== (int) $patient->id) {
            throw ValidationException::withMessages([
                'appointment_id' => 'The selected appointment does not belong to the selected patient.',
            ]);
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $payload['items'];
        [$normalizedItems, $totals] = $this->normalizeAndCalculateItems($items);

        $attempts = 0;

        while ($attempts < 3) {
            try {
                return DB::transaction(function () use ($clinicId, $userId, $payload, $normalizedItems, $totals): Invoice {
                    $invoice = Invoice::query()->create([
                        'clinic_id' => $clinicId,
                        'patient_id' => $payload['patient_id'],
                        'visit_id' => null,
                        'appointment_id' => $payload['appointment_id'] ?? null,
                        'issued_by' => null,
                        'invoice_number' => $payload['invoice_number'] ?? $this->generateInvoiceNumber($clinicId),
                        'status' => Invoice::STATUS_DRAFT,
                        'issued_at' => null,
                        'due_at' => $payload['due_at'] ?? null,
                        'subtotal_amount' => $totals['subtotal'],
                        'discount_amount' => $totals['discount'],
                        'tax_amount' => $totals['tax'],
                        'total_amount' => $totals['total'],
                        'paid_amount' => 0,
                        'balance_amount' => $totals['total'],
                        'notes' => $payload['notes'] ?? null,
                    ]);

                    foreach ($normalizedItems as $item) {
                        $invoice->items()->create([
                            'clinic_id' => $clinicId,
                            'service_code' => $item['service_code'],
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'discount_amount' => $item['discount_amount'],
                            'tax_amount' => $item['tax_amount'],
                            'line_total' => $item['line_total'],
                        ]);
                    }

                    $this->logAuditAction->handle(
                        clinicId: $clinicId,
                        userId: $userId,
                        action: 'billing.invoices.create',
                        auditable: $invoice,
                        newValues: $invoice->only([
                            'invoice_number',
                            'status',
                            'subtotal_amount',
                            'discount_amount',
                            'tax_amount',
                            'total_amount',
                            'balance_amount',
                        ]),
                        metadata: [
                            'items_count' => count($normalizedItems),
                        ],
                    );

                    return $invoice->fresh(['items', 'payments', 'patient']);
                });
            } catch (QueryException $exception) {
                $attempts++;

                if (! $this->isUniqueConstraintViolation($exception) || $attempts >= 3) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'invoice_number' => 'Unable to generate a unique invoice number. Please retry.',
        ]);
    }

    private function resolvePatient(int $clinicId, int $patientId): Patient
    {
        return Patient::query()
            ->forClinic($clinicId)
            ->whereKey($patientId)
            ->firstOrFail();
    }

    private function resolveAppointmentIfProvided(int $clinicId, mixed $appointmentId): ?Appointment
    {
        if ($appointmentId === null) {
            return null;
        }

        return Appointment::query()
            ->forClinic($clinicId)
            ->whereKey((int) $appointmentId)
            ->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: array{subtotal: float, discount: float, tax: float, total: float}}
     */
    private function normalizeAndCalculateItems(array $items): array
    {
        $normalizedItems = [];
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $itemDiscount = round((float) ($item['discount_amount'] ?? 0), 2);
            $itemTax = round((float) ($item['tax_amount'] ?? 0), 2);
            $base = round($quantity * $unitPrice, 2);
            $lineTotal = round(max(0, $base - $itemDiscount + $itemTax), 2);

            $subtotal += $base;
            $discount += $itemDiscount;
            $tax += $itemTax;
            $total += $lineTotal;

            $normalizedItems[] = [
                'service_code' => $item['service_code'] ?? null,
                'description' => (string) $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $itemDiscount,
                'tax_amount' => $itemTax,
                'line_total' => $lineTotal,
            ];
        }

        return [
            $normalizedItems,
            [
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax' => round($tax, 2),
                'total' => round($total, 2),
            ],
        ];
    }

    private function generateInvoiceNumber(int $clinicId): string
    {
        $today = now()->toDateString();
        $sequence = (int) Invoice::query()
            ->forClinic($clinicId)
            ->whereDate('created_at', $today)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('INV-%s-%04d', now()->format('Ymd'), $sequence);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return $sqlState === '23000' || $sqlState === '23505';
    }
}
