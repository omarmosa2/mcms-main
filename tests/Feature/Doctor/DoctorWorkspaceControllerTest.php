<?php

namespace Tests\Feature\Doctor;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DoctorWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_loads_patient_for_doctor_appointment_when_patient_belongs_to_another_clinic(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        try {
            $doctorClinic = Clinic::factory()->create();
            $patientClinic = Clinic::factory()->create();
            $doctor = User::factory()->create(['clinic_id' => $doctorClinic->id]);
            app(AssignUserRoleAction::class)->handle($doctor, 'doctor');

            DoctorProfile::factory()->create([
                'clinic_id' => $doctorClinic->id,
                'user_id' => $doctor->id,
                'is_active' => true,
            ]);

            $patient = Patient::factory()->create([
                'clinic_id' => $patientClinic->id,
                'first_name' => 'Omar',
                'last_name' => 'Saleh',
                'file_number' => 321,
            ]);

            $appointment = Appointment::factory()->create([
                'clinic_id' => $doctorClinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_for' => '2026-06-28 12:00:00',
                'status' => Appointment::STATUS_SCHEDULED,
            ]);

            $response = $this->actingAs($doctor)->get(route('doctor.workspace'));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('doctor/Workspace')
                ->where('today_schedule.0.id', $appointment->id)
                ->where('today_schedule.0.patient.id', $patient->id)
                ->where('today_schedule.0.patient.first_name', 'Omar')
                ->where('today_schedule.0.patient.last_name', 'Saleh')
                ->where('today_schedule.0.patient.file_number', 321));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_today_appointments_loads_patient_for_doctor_appointment_when_patient_belongs_to_another_clinic(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        try {
            $doctorClinic = Clinic::factory()->create();
            $patientClinic = Clinic::factory()->create();
            $doctor = User::factory()->create(['clinic_id' => $doctorClinic->id]);
            app(AssignUserRoleAction::class)->handle($doctor, 'doctor');

            $patient = Patient::factory()->create([
                'clinic_id' => $patientClinic->id,
                'first_name' => 'Omar',
                'last_name' => 'Saleh',
                'file_number' => 321,
            ]);

            $appointment = Appointment::factory()->create([
                'clinic_id' => $doctorClinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_for' => '2026-06-28 12:00:00',
                'status' => Appointment::STATUS_SCHEDULED,
            ]);

            $response = $this->actingAs($doctor)->get(route('doctor.today-appointments'));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('doctor/TodayAppointments')
                ->where('appointments.0.id', $appointment->id)
                ->where('appointments.0.patient.id', $patient->id)
                ->where('appointments.0.patient.first_name', 'Omar')
                ->where('appointments.0.patient.last_name', 'Saleh')
                ->where('appointments.0.patient.file_number', 321));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_doctor_can_complete_today_appointment_from_today_appointments_page(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        try {
            $clinic = Clinic::factory()->create();
            $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
            app(AssignUserRoleAction::class)->handle($doctor, 'doctor');

            $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

            $appointment = Appointment::factory()->create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_for' => '2026-06-28 12:00:00',
                'status' => Appointment::STATUS_SCHEDULED,
                'arrived_at' => null,
                'completed_at' => null,
            ]);

            $response = $this
                ->actingAs($doctor)
                ->from(route('doctor.today-appointments'))
                ->patch(route('appointments.transition-status', ['appointmentId' => $appointment->id]), [
                    'status' => Appointment::STATUS_COMPLETED,
                ]);

            $response->assertRedirect(route('doctor.today-appointments'));

            $appointment->refresh();

            $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
            $this->assertNotNull($appointment->arrived_at);
            $this->assertNotNull($appointment->completed_at);
        } finally {
            Carbon::setTestNow();
        }
    }
}
