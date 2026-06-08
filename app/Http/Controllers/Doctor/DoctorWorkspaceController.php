<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\FollowUp;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoctorWorkspaceController extends Controller
{
    public function workspace(Request $request): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $clinic = Clinic::query()->find($clinicId);
        $doctorProfile = $user->doctorProfile;
        $department = $doctorProfile?->department;

        $today = now()->toDateString();

        $todayAppointmentsCount = Appointment::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereDate('scheduled_for', $today)
            ->count();

        $examinedTodayCount = Appointment::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereDate('completed_at', $today)
            ->count();

        $pendingFollowUpsCount = FollowUp::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [FollowUp::STATUS_SCHEDULED])
            ->count();

        $activeTreatmentPlansCount = TreatmentPlan::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [TreatmentPlan::STATUS_NEW, TreatmentPlan::STATUS_IN_PROGRESS])
            ->count();

        $todaySchedule = Appointment::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereDate('scheduled_for', $today)
            ->with(['patient:id,clinic_id,first_name,last_name,file_number'])
            ->orderBy('scheduled_for')
            ->get()
            ->map(function ($appointment) {
                $medicalRecordId = $appointment->medicalRecords()->latest()->value('id');

                return [
                    'id' => $appointment->id,
                    'scheduled_for' => $appointment->scheduled_for,
                    'status' => $appointment->status,
                    'appointment_type' => $appointment->appointment_type,
                    'patient' => $appointment->patient,
                    'medical_record_id' => $medicalRecordId,
                ];
            });

        $upcomingAppointments = Appointment::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->where('scheduled_for', '>', now())
            ->whereDate('scheduled_for', '>', $today)
            ->with(['patient:id,clinic_id,first_name,last_name,file_number'])
            ->orderBy('scheduled_for')
            ->limit(10)
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'scheduled_for' => $appointment->scheduled_for,
                    'status' => $appointment->status,
                    'appointment_type' => $appointment->appointment_type,
                    'patient' => $appointment->patient,
                ];
            });

        return Inertia::render('doctor/Workspace', [
            'clinic' => [
                'name' => $clinic?->name,
                'department_name' => $department?->name,
                'clinic_type' => $department?->clinic_type,
            ],
            'doctor' => [
                'name' => $user->name,
                'specialty' => $doctorProfile?->specialty,
            ],
            'stats' => [
                'today_appointments' => $todayAppointmentsCount,
                'examined_today' => $examinedTodayCount,
                'pending_follow_ups' => $pendingFollowUpsCount,
                'active_treatment_plans' => $activeTreatmentPlansCount,
            ],
            'today_schedule' => $todaySchedule,
            'upcoming_appointments' => $upcomingAppointments,
        ]);
    }

    public function todayAppointments(Request $request): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;
        $today = now()->toDateString();

        $appointments = Appointment::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereDate('scheduled_for', $today)
            ->with(['patient:id,clinic_id,first_name,last_name,file_number,phone,date_of_birth,gender'])
            ->orderBy('scheduled_for')
            ->get()
            ->map(function ($appointment) {
                $medicalRecordId = $appointment->medicalRecords()->latest()->value('id');

                return [
                    'id' => $appointment->id,
                    'scheduled_for' => $appointment->scheduled_for,
                    'status' => $appointment->status,
                    'appointment_type' => $appointment->appointment_type,
                    'duration_minutes' => $appointment->duration_minutes,
                    'cost' => $appointment->cost,
                    'notes' => $appointment->notes,
                    'patient' => $appointment->patient,
                    'medical_record_id' => $medicalRecordId,
                ];
            });

        return Inertia::render('doctor/TodayAppointments', [
            'appointments' => $appointments,
            'date' => $today,
        ]);
    }

    public function followUps(Request $request): Response
    {
        $user = $request->user();
        $clinicId = (int) $user->clinic_id;
        $doctorId = (int) $user->id;

        $query = FollowUp::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->with(['patient:id,clinic_id,first_name,last_name,file_number', 'medicalRecord:id,clinic_id,primary_diagnosis'])
            ->orderByDesc('follow_up_date');

        $status = $request->get('status');
        if ($status) {
            $query->where('status', $status);
        }

        $followUps = $query->paginate(15);

        return Inertia::render('doctor/FollowUps', [
            'follow_ups' => $followUps,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        $user = $request->user();
        $doctorProfile = $user->doctorProfile;
        $department = $doctorProfile?->department;
        $clinic = $user->clinic;

        return Inertia::render('doctor/Profile', [
            'doctor' => [
                'name' => $user->name,
                'email' => $user->email,
                'specialty' => $doctorProfile?->specialty,
                'license_number' => $doctorProfile?->license_number,
                'phone' => $doctorProfile?->phone,
                'gender' => $doctorProfile?->gender,
                'bio' => $doctorProfile?->bio,
                'status' => $doctorProfile?->status,
                'consultation_duration_minutes' => $doctorProfile?->consultation_duration_minutes,
            ],
            'clinic' => [
                'name' => $clinic?->name,
                'department_name' => $department?->name,
                'clinic_type' => $department?->clinic_type,
            ],
            'work_schedule' => $doctorProfile?->work_schedule ?? [],
        ]);
    }
}
