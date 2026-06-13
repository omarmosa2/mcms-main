<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $chronicConditions = $this->relationLoaded('chronicConditions')
            ? $this->chronicConditions
                ->pluck('condition')
                ->filter(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '')
                ->values()
                ->all()
            : [];

        $allergies = $this->relationLoaded('allergies')
            ? $this->allergies
                ->pluck('allergy')
                ->filter(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '')
                ->values()
                ->all()
            : [];

        $currentMedications = $this->relationLoaded('medications')
            ? $this->medications
                ->pluck('medication')
                ->filter(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '')
                ->values()
                ->all()
            : [];

        $attachments = $this->relationLoaded('attachments')
            ? PatientAttachmentResource::collection($this->attachments)->resolve()
            : [];

        $appointments = $this->relationLoaded('appointments')
            ? $this->appointments
                ->sortByDesc('scheduled_for')
                ->values()
                ->map(fn ($appointment): array => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'doctor' => $appointment->doctor !== null ? [
                        'id' => $appointment->doctor->id,
                        'name' => $appointment->doctor->name,
                    ] : null,
                    'scheduled_for' => $appointment->scheduled_for?->toISOString(),
                    'duration_minutes' => $appointment->duration_minutes,
                    'notes' => $appointment->notes,
                    'created_at' => $appointment->created_at?->toISOString(),
                ])
                ->all()
            : [];

        $invoices = $this->relationLoaded('invoices')
            ? $this->invoices
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($invoice): array => [
                    'id' => $invoice->id,
                    'status' => $invoice->status,
                    'subtotal_amount' => $invoice->subtotal_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'balance_amount' => $invoice->balance_amount,
                    'issued_at' => $invoice->issued_at?->toISOString(),
                    'created_at' => $invoice->created_at?->toISOString(),
                ])
                ->all()
            : [];

        $prescriptions = $this->relationLoaded('prescriptions')
            ? $this->prescriptions
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($prescription): array => [
                    'id' => $prescription->id,
                    'status' => $prescription->status,
                    'prescriber' => $prescription->prescriber !== null ? [
                        'id' => $prescription->prescriber->id,
                        'name' => $prescription->prescriber->name,
                    ] : null,
                    'notes' => $prescription->notes,
                    'issued_at' => $prescription->issued_at?->toISOString(),
                    'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                    'created_at' => $prescription->created_at?->toISOString(),
                ])
                ->all()
            : [];

        $labOrders = $this->relationLoaded('labOrders')
            ? $this->labOrders
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($labOrder): array => [
                    'id' => $labOrder->id,
                    'status' => $labOrder->status,
                    'orderer' => $labOrder->orderer !== null ? [
                        'id' => $labOrder->orderer->id,
                        'name' => $labOrder->orderer->name,
                    ] : null,
                    'notes' => $labOrder->notes,
                    'ordered_at' => $labOrder->ordered_at?->toISOString(),
                    'created_at' => $labOrder->created_at?->toISOString(),
                ])
                ->all()
            : [];

        $radiologyOrders = $this->relationLoaded('radiologyOrders')
            ? $this->radiologyOrders
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($radiologyOrder): array => [
                    'id' => $radiologyOrder->id,
                    'status' => $radiologyOrder->status,
                    'orderer' => $radiologyOrder->orderer !== null ? [
                        'id' => $radiologyOrder->orderer->id,
                        'name' => $radiologyOrder->orderer->name,
                    ] : null,
                    'notes' => $radiologyOrder->notes,
                    'ordered_at' => $radiologyOrder->ordered_at?->toISOString(),
                    'created_at' => $radiologyOrder->created_at?->toISOString(),
                ])
                ->all()
            : [];

        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'file_number' => $this->file_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->date_of_birth?->age,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'email' => $this->email,
            'national_id' => $this->shouldExposeNationalId($request)
                ? $this->national_id
                : $this->maskedNationalId(),
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'notes' => $this->notes,
            'chronic_conditions' => $chronicConditions,
            'allergies' => $allergies,
            'current_medications' => $currentMedications,
            'attachments' => $attachments,
            'appointments' => $appointments,
            'invoices' => $invoices,
            'prescriptions' => $prescriptions,
            'lab_orders' => $labOrders,
            'radiology_orders' => $radiologyOrders,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function shouldExposeNationalId(Request $request): bool
    {
        $cacheKey = 'patients.can_expose_national_id';

        if ($request->attributes->has($cacheKey)) {
            return (bool) $request->attributes->get($cacheKey);
        }

        $user = $request->user();

        if ($user === null) {
            $request->attributes->set($cacheKey, false);

            return false;
        }

        $canExposeNationalId = $user->hasPermission('patient.national_id.view')
            || $user->hasPermission('patient.*');

        $request->attributes->set($cacheKey, $canExposeNationalId);

        return $canExposeNationalId;
    }

    private function maskedNationalId(): ?string
    {
        $nationalId = $this->national_id;

        if ($nationalId === null || trim((string) $nationalId) === '') {
            return null;
        }

        $normalized = trim((string) $nationalId);
        $length = strlen($normalized);
        $visibleDigits = min(4, $length);

        return str_repeat('*', max(0, $length - $visibleDigits)).substr($normalized, -$visibleDigits);
    }
}
