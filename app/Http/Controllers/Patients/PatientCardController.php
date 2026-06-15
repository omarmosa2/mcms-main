<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\StorePatientCardVisitRequest;
use App\Http\Requests\Patients\UpdatePatientCardVisitRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\PatientCardVisitResource;
use App\Http\Resources\PatientResource;
use App\Models\Appointment;
use App\Models\Department;
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

        $this->authorizeView($request);

        $patient = $this->patientQuery($clinicId)
            ->whereKey($patientId)
            ->firstOrFail();

        $visits = $this->visitsQuery($clinicId, $patientId)
            ->get();

        $appointmentId = $request->query('appointment_id');
        $activeAppointment = null;

        if ($appointmentId !== null) {
            $activeAppointment = Appointment::query()
                ->forClinic($clinicId)
                ->with([
                    'doctor:id,clinic_id,name',
                    'doctor.doctorProfile:id,clinic_id,user_id,department_id,specialty',
                    'doctor.doctorProfile.department:id,clinic_id,name,clinic_type',
                ])
                ->whereKey((int) $appointmentId)
                ->first();

            if ($activeAppointment !== null && $activeAppointment->patient_id !== (int) $patientId) {
                $activeAppointment = null;
            }
        }

        return Inertia::render('patients/Card', [
            'patient' => PatientResource::make($patient)->response()->getData(true),
            'visits' => PatientCardVisitResource::collection($visits)->response()->getData(true),
            'doctors' => $this->doctorOptions($clinicId, $user),
            'departments' => $this->departmentOptions($clinicId, $user),
            'card' => $this->cardMeta($request, $patient, $visits->first()),
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
        $patient = $this->patientQuery($clinicId)->whereKey($patientId)->firstOrFail();
        $payload = $this->normalizedPayload($request, $request->validated());

        $appointmentId = $request->validated('appointment_id');

        if ($appointmentId !== null) {
            $existingVisit = PatientCardVisit::query()
                ->forClinic($clinicId)
                ->where('appointment_id', $appointmentId)
                ->exists();

            if ($existingVisit) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => 'توجد زيارة طبية مسبقة مرتبطة بهذا الموعد.']);

                return to_route('patients.card.show', $patient->id, ['appointment_id' => $appointmentId]);
            }

            $appointment = Appointment::query()
                ->forClinic($clinicId)
                ->with(['doctor:id,clinic_id,name', 'doctor.doctorProfile:id,clinic_id,user_id,department_id'])
                ->whereKey($appointmentId)
                ->first();

            if ($appointment !== null) {
                $payload['doctor_id'] = $payload['doctor_id'] ?? $appointment->doctor_id;
                $payload['department_id'] = $payload['department_id'] ?? $appointment->doctor?->doctorProfile?->department_id;
                $payload['visit_date'] = $payload['visit_date'] ?? $appointment->scheduled_for->toDateString();
                $payload['visit_time'] = $payload['visit_time'] ?? $appointment->scheduled_for->format('H:i');
            }
        }

        PatientCardVisit::query()->create([
            ...$payload,
            'clinic_id' => $clinicId,
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
        $this->patientQuery($clinicId)->whereKey($patientId)->firstOrFail();

        $visit = PatientCardVisit::query()
            ->forClinic($clinicId)
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

        if (! $this->canManageVisits($request)) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'You do not have the required permission.');
        }

        $this->patientQuery($clinicId)->whereKey($patientId)->firstOrFail();

        $visit = PatientCardVisit::query()
            ->forClinic($clinicId)
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
        $this->authorizeView($request);

        $patient = $this->patientQuery($clinicId)
            ->whereKey($patientId)
            ->firstOrFail();

        $visits = $this->visitsQuery($clinicId, $patientId)->get();

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

    private function patientQuery(int $clinicId)
    {
        return Patient::query()
            ->forClinic($clinicId)
            ->with([
                'appointments' => fn ($query) => $query
                    ->with([
                        'doctor:id,clinic_id,name',
                        'doctor.doctorProfile:id,clinic_id,user_id,department_id,specialty',
                        'doctor.doctorProfile.department:id,clinic_id,name,clinic_type',
                    ])
                    ->latest('scheduled_for')
                    ->limit(1),
            ]);
    }

    private function visitsQuery(int $clinicId, int $patientId)
    {
        return PatientCardVisit::query()
            ->forClinic($clinicId)
            ->where('patient_id', $patientId)
            ->with(['doctor:id,clinic_id,name', 'department:id,clinic_id,name,clinic_type', 'appointment:id,clinic_id,patient_id,doctor_id,scheduled_for,appointment_number,status'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id');
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

        $departmentId = $payload['department_id'] ?? $this->departmentIdForDoctor($request, $doctorId);

        return [
            'visit_date' => $payload['visit_date'],
            'visit_time' => $payload['visit_time'] ?? null,
            'doctor_id' => $doctorId,
            'department_id' => $departmentId,
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

    private function departmentIdForDoctor(Request $request, mixed $doctorId): ?int
    {
        if ($doctorId === null) {
            return null;
        }

        return User::query()
            ->forClinic($this->resolveClinicId($request))
            ->with('doctorProfile:id,clinic_id,user_id,department_id')
            ->whereKey((int) $doctorId)
            ->first()
            ?->doctorProfile
            ?->department_id;
    }

    private function doctorOptions(int $clinicId, ?User $user): array
    {
        return User::query()
            ->forClinic($clinicId)
            ->with(['doctorProfile:id,clinic_id,user_id,department_id,specialty'])
            ->whereHas('roles', function ($query) use ($clinicId): void {
                $query->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->when($user?->hasRole('doctor') === true, fn ($query) => $query->whereKey($user->id))
            ->orderBy('name')
            ->get(['id', 'clinic_id', 'name'])
            ->map(fn (User $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'department_id' => $doctor->doctorProfile?->department_id,
                'specialty' => $doctor->doctorProfile?->specialty,
            ])
            ->all();
    }

    private function departmentOptions(int $clinicId, ?User $user): array
    {
        return Department::query()
            ->forClinic($clinicId)
            ->when($user?->hasRole('doctor') === true, function ($query) use ($user): void {
                $query->whereKey($user->doctorProfile?->department_id ?? 0);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'clinic_type'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'clinic_type' => $department->clinic_type,
            ])
            ->all();
    }

    private function cardMeta(Request $request, Patient $patient, ?PatientCardVisit $latestVisit): array
    {
        $latestAppointment = $patient->appointments->first();
        $doctor = $latestVisit?->doctor ?? $latestAppointment?->doctor;
        $department = $latestVisit?->department ?? $doctor?->doctorProfile?->department;

        return [
            'clinic_name' => $request->attributes->get('clinic_name') ?? $request->user()?->clinic?->name ?? config('app.name'),
            'project_name' => $request->user()?->clinic?->legal_name ?? $request->user()?->clinic?->name ?? config('app.name'),
            'page_number' => '1',
            'date' => now()->toDateString(),
            'doctor' => $doctor?->name,
            'department' => $department?->name,
        ];
    }

    private function currentUserData(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('doctorProfile:id,clinic_id,user_id,department_id,specialty');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'is_doctor' => $user->hasRole('doctor'),
            'doctor_id' => $user->hasRole('doctor') ? $user->id : null,
            'department_id' => $user->doctorProfile?->department_id,
        ];
    }
}
