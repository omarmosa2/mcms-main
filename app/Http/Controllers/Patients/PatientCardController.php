<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientCardVisitRequest;
use App\Http\Requests\Patients\UpdatePatientCardVisitRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\PatientCardVisitResource;
use App\Http\Resources\PatientResource;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientCardVisit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PatientCardController extends Controller
{
    public function show(Request $request, int $patientId): InertiaResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $user = $request->user();
        $includeAllClinics = $this->canViewAllClinics($request);
        $isDoctorWithAppointment = $this->isDoctorWithAppointment($request, $patientId);

        $this->authorizeView($request);

        $patient = $this->patientQuery($clinicId, $includeAllClinics, $isDoctorWithAppointment, $user?->id)
            ->whereKey($patientId)
            ->first();

        if (! $patient) {
            abort(404, 'Patient not found');
        }

        $visits = $this->visitsQuery($clinicId, $patientId, $includeAllClinics, $isDoctorWithAppointment, $user?->id)
            ->get();

        $activeAppointment = $this->activeAppointment($request, $clinicId, $patientId, $includeAllClinics);

        return Inertia::render('patients/Card', [
            'patient' => PatientResource::make($patient)->response()->getData(true),
            'visits' => PatientCardVisitResource::collection($visits)->response()->getData(true),
            'doctors' => $this->doctorOptions($clinicId, $user, $includeAllClinics),
            'clinics' => $this->clinicOptions($clinicId, $user, $includeAllClinics),
            'card' => $this->cardMeta($request, $patient, $visits->first(), $activeAppointment),
            'permissions' => [
                'can_manage_visits' => $this->canManageVisits($request),
                'can_manage_appointments' => $user?->hasPermission('appointment.view') ?? false,
            ],
            'activeAppointment' => $activeAppointment !== null
                ? AppointmentResource::make($activeAppointment)->response()->getData(true)['data']
                : null,
            'currentUser' => $this->currentUserData($user),
        ]);
    }

    public function store(StorePatientCardVisitRequest $request, int $patientId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request);
        $isDoctorWithAppointment = $this->isDoctorWithAppointment($request, $patientId);
        $patient = $this->patientQuery($clinicId, $includeAllClinics, $isDoctorWithAppointment, $request->user()?->id)->whereKey($patientId)->firstOrFail();
        $payload = $this->normalizedPayload($request, $request->validated());

        $appointmentId = $request->validated('appointment_id');

        if ($appointmentId !== null) {
            $visitCheckQuery = $includeAllClinics
                ? PatientCardVisit::query()->withoutGlobalScope('clinic')
                : PatientCardVisit::query()->forClinic($clinicId);

            $existingVisit = $visitCheckQuery
                ->where('appointment_id', $appointmentId)
                ->exists();

            if ($existingVisit) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => 'توجد زيارة طبية مسبقة مرتبطة بهذا الموعد.']);

                return to_route('patients.card.show', $patient->id, ['appointment_id' => $appointmentId]);
            }

            $appointmentQuery = $includeAllClinics
                ? Appointment::query()->withoutGlobalScope('clinic')
                : Appointment::query()->forClinic($clinicId);

            $appointment = $appointmentQuery
                ->with(['doctor:id,clinic_id,name', 'doctor.doctorProfile:id,clinic_id,user_id'])
                ->whereKey($appointmentId)
                ->first();

            if ($appointment !== null) {
                $payload['doctor_id'] = $payload['doctor_id'] ?? $appointment->doctor_id;
                $payload['visit_date'] = $payload['visit_date'] ?? $appointment->scheduled_for->toDateString();
                $payload['visit_time'] = $payload['visit_time'] ?? $appointment->scheduled_for->format('H:i');
            }
        }

        PatientCardVisit::query()->create([
            ...$payload,
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointmentId,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تمت إضافة الزيارة إلى بطاقة المريض.']);

        return to_route('patients.card.show', $patient->id);
    }

    public function update(UpdatePatientCardVisitRequest $request, int $patientId, int $visitId): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request);
        $isDoctorWithAppointment = $this->isDoctorWithAppointment($request, $patientId);
        $this->patientQuery($clinicId, $includeAllClinics, $isDoctorWithAppointment, $request->user()?->id)->whereKey($patientId)->firstOrFail();

        $visitQuery = $includeAllClinics
            ? PatientCardVisit::query()->withoutGlobalScope('clinic')
            : PatientCardVisit::query()->forClinic($clinicId);

        $visit = $visitQuery
            ->where('patient_id', $patientId)
            ->whereKey($visitId)
            ->firstOrFail();

        $visit->update([
            ...$this->normalizedPayload($request, $request->validated()),
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم تحديث الزيارة.']);

        return to_route('patients.card.show', $patientId);
    }

    public function destroy(Request $request, int $patientId, int $visitId): RedirectResponse|JsonResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request);
        $isDoctorWithAppointment = $this->isDoctorWithAppointment($request, $patientId);

        if (! $this->canManageVisits($request)) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'You do not have the required permission.');
        }

        $this->patientQuery($clinicId, $includeAllClinics, $isDoctorWithAppointment, $request->user()?->id)->whereKey($patientId)->firstOrFail();

        $visitQuery = $includeAllClinics
            ? PatientCardVisit::query()->withoutGlobalScope('clinic')
            : PatientCardVisit::query()->forClinic($clinicId);

        $visit = $visitQuery
            ->where('patient_id', $patientId)
            ->whereKey($visitId)
            ->firstOrFail();

        $visit->delete();

        if ($request->expectsJson()) {
            return response()->json(null, SymfonyResponse::HTTP_NO_CONTENT);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حذف الزيارة.']);

        return to_route('patients.card.show', $patientId);
    }

    public function exportPdf(Request $request, int $patientId): SymfonyResponse
    {
        $clinicId = $this->resolveClinicId($request);
        $includeAllClinics = $this->canViewAllClinics($request);
        $isDoctorWithAppointment = $this->isDoctorWithAppointment($request, $patientId);
        $this->authorizeView($request);

        $patient = $this->patientQuery($clinicId, $includeAllClinics, $isDoctorWithAppointment, $request->user()?->id)
            ->whereKey($patientId)
            ->firstOrFail();

        $visits = $this->visitsQuery($clinicId, $patientId, $includeAllClinics, $isDoctorWithAppointment, $request->user()?->id)->get();

        $pdf = Pdf::loadView('exports.patient-card', [
            'patient' => $patient,
            'visits' => $visits,
            'card' => $this->cardMeta($request, $patient, $visits->first()),
        ])
            ->setPaper('a4')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
            ]);

        return $pdf->download(sprintf('patient-card-%s.pdf', $patient->file_number ?? $patient->id));
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();

        if (
            $user === null
            || (! $user->hasPermission('patient.view') && ! $user->hasPermission('medical_record.view') && ! $user->hasPermission('patient_card.view'))
        ) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'You do not have the required permission.');
        }
    }

    private function canManageVisits(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && ($user->hasPermission('medical_record.update') || $user->hasPermission('patient_card.update'));
    }

    private function canViewAllClinics(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('clinic_admin') || $user->hasRole('receptionist'));
    }

    private function isDoctorWithAppointment(Request $request, int $patientId): bool
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole('doctor')) {
            return false;
        }

        return Appointment::query()
            ->withoutGlobalScope('clinic')
            ->where('doctor_id', $user->id)
            ->where('patient_id', $patientId)
            ->exists();
    }

    private function patientQuery(int $clinicId, bool $includeAllClinics = false, bool $isDoctorWithAppointment = false, ?int $doctorId = null)
    {
        if ($includeAllClinics) {
            $query = Patient::query()->withoutGlobalScope('clinic');
        } elseif ($isDoctorWithAppointment && $doctorId !== null) {
            $query = Patient::query()->withoutGlobalScope('clinic');
        } else {
            $query = Patient::query()->forClinic($clinicId);
        }

        return $query->with([
            'appointments' => fn ($query) => $query
                ->withoutGlobalScope('clinic')
                ->with([
                    'doctor:id,clinic_id,name',
                    'doctor.doctorProfile:id,clinic_id,user_id,specialty',
                    'doctor.doctorProfile.clinic:id,name',
                ])
                ->latest('scheduled_for')
                ->limit(1),
        ]);
    }

    private function visitsQuery(int $clinicId, int $patientId, bool $includeAllClinics = false, bool $isDoctorWithAppointment = false, ?int $doctorId = null)
    {
        if ($includeAllClinics || ($isDoctorWithAppointment && $doctorId !== null)) {
            $query = PatientCardVisit::query()->withoutGlobalScope('clinic');
        } else {
            $query = PatientCardVisit::query()->forClinic($clinicId);
        }

        return $query
            ->where('patient_id', $patientId)
            ->with(['doctor:id,clinic_id,name', 'clinic:id,name', 'appointment:id,clinic_id,patient_id,doctor_id,scheduled_for,appointment_number,status'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id');
    }

    private function activeAppointment(Request $request, int $clinicId, int $patientId, bool $includeAllClinics): ?Appointment
    {
        $appointmentQuery = $includeAllClinics
            ? Appointment::query()->withoutGlobalScope('clinic')
            : Appointment::query()->forClinic($clinicId);

        $appointmentQuery
            ->with([
                'clinic:id,name',
                'doctor:id,clinic_id,name',
                'doctor.doctorProfile:id,clinic_id,user_id,specialty',
                'doctor.doctorProfile.clinic:id,name',
            ])
            ->where('patient_id', $patientId);

        $appointmentId = $request->query('appointment_id');

        if ($appointmentId !== null) {
            return $appointmentQuery
                ->whereKey((int) $appointmentId)
                ->first();
        }

        return $appointmentQuery
            ->whereDate('scheduled_for', now()->toDateString())
            ->whereNotIn('status', [Appointment::STATUS_CANCELED, Appointment::STATUS_NO_SHOW])
            ->orderBy('scheduled_for')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request, array $payload): array
    {
        $user = $request->user();
        $doctorId = $user?->hasRole('doctor')
            ? (int) $user->id
            : ($payload['doctor_id'] ?? null);

        return [
            'visit_date' => $payload['visit_date'],
            'visit_time' => $payload['visit_time'] ?? null,
            'doctor_id' => $doctorId,
            'visit_reason' => $payload['visit_reason'] ?? null,
            'chief_complaint' => $payload['chief_complaint'] ?? null,
            'general_notes' => $payload['general_notes'] ?? null,
            'new_symptoms' => $payload['new_symptoms'] ?? null,
            'medical_or_surgical_complaint' => $payload['medical_or_surgical_complaint'] ?? null,
            'diagnosis' => $payload['diagnosis'] ?? null,
            'prescribed_treatment_or_referral' => $payload['prescribed_treatment_or_referral'] ?? null,
            'signature' => $payload['signature'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function doctorOptions(int $clinicId, ?User $user, bool $includeAllClinics = false): array
    {
        $query = $includeAllClinics
            ? User::query()->withoutGlobalScope('clinic')
            : User::query()->forClinic($clinicId);

        return $query
            ->with(['doctorProfile:id,clinic_id,user_id,specialty'])
            ->whereHas('roles', function ($query) use ($clinicId, $includeAllClinics): void {
                if ($includeAllClinics) {
                    $query->where('roles.name', 'doctor');
                } else {
                    $query->where('roles.clinic_id', $clinicId)
                        ->where('roles.name', 'doctor');
                }
            })
            ->when($user?->hasRole('doctor') === true, fn ($query) => $query->whereKey($user->id))
            ->orderBy('name')
            ->get(['id', 'clinic_id', 'name'])
            ->map(fn (User $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'clinic_id' => $doctor->doctorProfile?->clinic_id,
                'specialty' => $doctor->doctorProfile?->specialty,
            ])
            ->all();
    }

    private function clinicOptions(int $clinicId, ?User $user = null, bool $includeAllClinics = false): array
    {
        $query = $includeAllClinics
            ? Clinic::query()
            : Clinic::query()->whereKey($clinicId);

        return $query
            ->clinical()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Clinic $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->name,
            ])
            ->all();
    }

    private function cardMeta(Request $request, Patient $patient, ?PatientCardVisit $latestVisit, ?Appointment $activeAppointment = null): array
    {
        $latestAppointment = $activeAppointment ?? $patient->appointments->first();
        $doctor = $latestVisit?->doctor ?? $latestAppointment?->doctor;
        $clinic = $latestVisit?->clinic ?? $doctor?->doctorProfile?->clinic ?? $latestAppointment?->clinic;

        return [
            'clinic_name' => $request->attributes->get('clinic_name') ?? $request->user()?->clinic?->name ?? config('app.name'),
            'project_name' => $request->user()?->clinic?->legal_name ?? $request->user()?->clinic?->name ?? config('app.name'),
            'page_number' => '1',
            'date' => now()->toDateString(),
            'doctor' => $doctor?->name,
            'clinic' => $clinic?->name,
        ];
    }

    private function currentUserData(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('doctorProfile:id,clinic_id,user_id,specialty');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'is_doctor' => $user->hasRole('doctor'),
            'doctor_id' => $user->hasRole('doctor') ? $user->id : null,
            'clinic_id' => $user->doctorProfile?->clinic_id,
        ];
    }
}
