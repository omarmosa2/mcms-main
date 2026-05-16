<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Audit\LogAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePortalAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientPortalToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PatientPortalController extends Controller
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function issueToken(Request $request, int $patientId): JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $patient = Patient::query()
            ->forClinic($clinicId)
            ->whereKey($patientId)
            ->firstOrFail();

        ['plain_token' => $plainToken, 'token_hash' => $tokenHash] = PatientPortalToken::generateTokenPair();
        $expiresAt = now()->addDays(7);

        $portalToken = PatientPortalToken::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'created_by' => $request->user()?->id,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: (int) $request->user()->id,
            action: 'portal.tokens.create',
            auditable: $patient,
            metadata: [
                'portal_token_id' => $portalToken->id,
                'expires_at' => $expiresAt->toISOString(),
            ],
        );

        return response()->json([
            'data' => [
                'token_id' => $portalToken->id,
                'expires_at' => $expiresAt->toISOString(),
                'portal_url' => route('portal.show', ['plainToken' => $plainToken]),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(string $plainToken): InertiaResponse
    {
        $portalToken = $this->resolveActiveToken($plainToken);
        $patient = $portalToken->patient;
        $appointments = Appointment::query()
            ->forClinic((int) $portalToken->clinic_id)
            ->where('patient_id', $patient->id)
            ->whereIn('status', [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_ARRIVED,
            ])
            ->orderBy('scheduled_for')
            ->get(['id', 'appointment_number', 'scheduled_for', 'duration_minutes', 'status', 'cancel_reason', 'notes']);

        $portalToken->markUsed();

        return Inertia::render('portal/Index', [
            'token' => $plainToken,
            'expires_at' => $portalToken->expires_at?->toISOString(),
            'patient' => [
                'id' => $patient->id,
                'full_name' => trim("{$patient->first_name} {$patient->last_name}"),
                'file_number' => $patient->file_number,
                'phone' => $patient->phone,
            ],
            'appointments' => $appointments->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'scheduled_for' => $appointment->scheduled_for?->toISOString(),
                'duration_minutes' => (int) $appointment->duration_minutes,
                'status' => $appointment->status,
                'cancel_reason' => $appointment->cancel_reason,
                'notes' => $appointment->notes,
            ])->values(),
            'actions' => [
                'can_reschedule' => true,
                'can_cancel' => true,
            ],
        ]);
    }

    public function updateAppointment(
        UpdatePortalAppointmentRequest $request,
        string $plainToken,
        int $appointmentId,
    ): JsonResponse {
        $portalToken = $this->resolveActiveToken($plainToken);
        $clinicId = (int) $portalToken->clinic_id;
        $payload = $request->validated();

        $appointment = Appointment::query()
            ->forClinic($clinicId)
            ->where('patient_id', $portalToken->patient_id)
            ->whereKey($appointmentId)
            ->firstOrFail();

        if (in_array($appointment->status, Appointment::TERMINAL_STATUSES, true)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Terminal appointments cannot be modified in the portal.');
        }

        if ($payload['action'] === 'reschedule') {
            $appointment->scheduled_for = $payload['scheduled_for'];
            $appointment->status = Appointment::STATUS_CONFIRMED;
            $appointment->cancel_reason = null;
            $appointment->canceled_at = null;
            $appointment->save();
        }

        if ($payload['action'] === 'cancel') {
            $appointment->status = Appointment::STATUS_CANCELED;
            $appointment->cancel_reason = $payload['cancel_reason'];
            $appointment->canceled_at = now();
            $appointment->save();
        }

        $portalToken->markUsed();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: null,
            action: 'portal.appointments.update',
            auditable: $appointment,
            metadata: [
                'portal_token_id' => $portalToken->id,
                'action' => $payload['action'],
            ],
        );

        return response()->json([
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'scheduled_for' => $appointment->scheduled_for?->toISOString(),
                'cancel_reason' => $appointment->cancel_reason,
            ],
        ]);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    private function resolveActiveToken(string $plainToken): PatientPortalToken
    {
        $tokenHash = PatientPortalToken::hashToken($plainToken);

        return PatientPortalToken::query()
            ->active()
            ->with('patient:id,clinic_id,file_number,first_name,last_name,phone')
            ->where('token_hash', $tokenHash)
            ->firstOrFail();
    }
}
