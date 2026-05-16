<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $invoiceId, int $userId, array $payload): Invoice
    {
        $invoice = Invoice::query()
            ->forClinic($clinicId)
            ->with('items')
            ->findOrFail($invoiceId);

        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be updated.',
            ]);
        }

        $patient = array_key_exists('patient_id', $payload)
            ? $this->resolvePatient($clinicId, (int) $payload['patient_id'])
            : $this->resolvePatient($clinicId, (int) $invoice->patient_id);

        $visit = array_key_exists('visit_id', $payload)
            ? $this->resolveVisitIfProvided($clinicId, $payload['visit_id'])
            : $this->resolveVisitIfProvided($clinicId, $invoice->visit_id);

        $appointment = array_key_exists('appointment_id', $payload)
            ? $this->resolveAppointmentIfProvided($clinicId, $payload['appointment_id'])
            : $this->resolveAppointmentIfProvided($clinicId, $invoice->appointment_id);

        $this->ensureVisitAndAppointmentBelongToPatient(
            patientId: (int) $patient->id,
            visit: $visit,
            appointment: $appointment,
        );

        $payload['patient_id'] = $patient->id;
        $payload['visit_id'] = $visit?->id;
        $payload['appointment_id'] = $appointment?->id;

        $oldValues = $invoice->only([
            'patient_id',
            'visit_id',
            'appointment_id',
            'invoice_number',
            'due_at',
            'subtotal_amount',
            'discount_amount',
            'tax_amount',
            'total_amount',
            'balance_amount',
            'notes',
        ]);

        return DB::transaction(function () use ($invoice, $clinicId, $userId, $payload, $oldValues): Invoice {
            $invoice->fill([
                'patient_id' => $payload['patient_id'] ?? $invoice->patient_id,
                'visit_id' => $payload['visit_id'] ?? $invoice->visit_id,
                'appointment_id' => $payload['appointment_id'] ?? $invoice->appointment_id,
                'invoice_number' => $payload['invoice_number'] ?? $invoice->invoice_number,
                'due_at' => $payload['due_at'] ?? $invoice->due_at,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $invoice->notes,
            ]);

            if (array_key_exists('items', $payload)) {
                /** @var array<int, array<string, mixed>> $items */
                $items = $payload['items'];
                [$normalizedItems, $totals] = $this->normalizeAndCalculateItems($items);

                $invoice->subtotal_amount = $totals['subtotal'];
                $invoice->discount_amount = $totals['discount'];
                $invoice->tax_amount = $totals['tax'];
                $invoice->total_amount = $totals['total'];
                $invoice->paid_amount = 0;
                $invoice->balance_amount = $totals['total'];

                $invoice->items()->delete();

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
            }

            $invoice->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'billing.invoices.update',
                auditable: $invoice,
                oldValues: $oldValues,
                newValues: $invoice->only([
                    'patient_id',
                    'visit_id',
                    'appointment_id',
                    'invoice_number',
                    'due_at',
                    'subtotal_amount',
                    'discount_amount',
                    'tax_amount',
                    'total_amount',
                    'balance_amount',
                    'notes',
                ]),
                metadata: [
                    'items_replaced' => array_key_exists('items', $payload),
                ],
            );

            return $invoice->fresh(['items', 'payments', 'patient']);
        });
    }

    private function resolvePatient(int $clinicId, int $patientId): Patient
    {
        return Patient::query()
            ->forClinic($clinicId)
            ->whereKey($patientId)
            ->firstOrFail();
    }

    private function resolveVisitIfProvided(int $clinicId, mixed $visitId): ?Visit
    {
        if ($visitId === null) {
            return null;
        }

        return Visit::query()
            ->forClinic($clinicId)
            ->whereKey((int) $visitId)
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

    private function ensureVisitAndAppointmentBelongToPatient(
        int $patientId,
        ?Visit $visit,
        ?Appointment $appointment,
    ): void {
        if ($visit !== null && (int) $visit->patient_id !== $patientId) {
            throw ValidationException::withMessages([
                'visit_id' => 'The selected visit does not belong to the selected patient.',
            ]);
        }

        if ($appointment !== null && (int) $appointment->patient_id !== $patientId) {
            throw ValidationException::withMessages([
                'appointment_id' => 'The selected appointment does not belong to the selected patient.',
            ]);
        }
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
}
