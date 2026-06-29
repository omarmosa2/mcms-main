<?php

namespace Tests\Feature\Patients;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\PatientCardVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientCardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_patient_card_with_only_that_patients_visits_ordered_newest_first(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $otherPatient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $olderVisit = PatientCardVisit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_date' => '2026-06-01',
            'diagnosis' => 'Older diagnosis',
        ]);

        $newerVisit = PatientCardVisit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_date' => '2026-06-10',
            'diagnosis' => 'Newer diagnosis',
        ]);

        PatientCardVisit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $otherPatient->id,
            'visit_date' => '2026-06-15',
        ]);

        $response = $this->get(route('patients.card.show', ['patientId' => $patient->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('patients/Card')
            ->where('patient.data.id', $patient->id)
            ->has('visits.data', 2)
            ->where('visits.data.0.id', $newerVisit->id)
            ->where('visits.data.1.id', $olderVisit->id)
            ->where('permissions.can_manage_visits', true)
        );
    }

    public function test_patient_card_loads_todays_booked_appointment_when_opened_directly(): void
    {
        Carbon::setTestNow('2026-06-29 09:00:00');

        try {
            $clinic = Clinic::factory()->create(['name' => 'Today Clinic']);
            $this->authenticateForClinic($clinic, 'clinic_admin');
            $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
            $doctor = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Dr Today']);

            Appointment::factory()->create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_for' => '2026-06-28 10:00:00',
            ]);

            $todaysAppointment = Appointment::factory()->create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_number' => 'APT-TODAY',
                'scheduled_for' => '2026-06-29 11:00:00',
                'status' => Appointment::STATUS_CONFIRMED,
            ]);

            $response = $this->get(route('patients.card.show', ['patientId' => $patient->id]));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->where('activeAppointment.id', $todaysAppointment->id)
                ->where('activeAppointment.appointment_number', 'APT-TODAY')
                ->where('activeAppointment.doctor.name', 'Dr Today')
                ->where('activeAppointment.doctor.clinic.name', 'Today Clinic')
                ->where('activeAppointment.clinic.name', 'Today Clinic')
                ->where('card.doctor', 'Dr Today')
                ->where('card.clinic', 'Today Clinic')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_doctor_can_store_update_and_delete_patient_card_visit_for_their_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
        ]);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $storeResponse = $this->post(route('patients.card.visits.store', ['patientId' => $patient->id]), [
            'visit_date' => '2026-06-15',
            'visit_reason' => 'Follow up',
            'chief_complaint' => 'Fever',
            'new_symptoms' => 'Cough',
            'medical_or_surgical_complaint' => 'Respiratory symptoms',
            'diagnosis' => 'Viral infection',
            'prescribed_treatment_or_referral' => 'Rest and fluids',
            'signature' => 'Dr Signature',
            'notes' => 'Stable',
        ]);

        $storeResponse->assertRedirect(route('patients.card.show', ['patientId' => $patient->id]));

        $visit = PatientCardVisit::query()->firstOrFail();

        $this->assertDatabaseHas('patient_card_visits', [
            'id' => $visit->id,
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Viral infection',
        ]);

        $updateResponse = $this->patch(route('patients.card.visits.update', [
            'patientId' => $patient->id,
            'visitId' => $visit->id,
        ]), [
            'visit_date' => '2026-06-16',
            'diagnosis' => 'Updated diagnosis',
            'notes' => 'Improved',
        ]);

        $updateResponse->assertRedirect(route('patients.card.show', ['patientId' => $patient->id]));
        $this->assertDatabaseHas('patient_card_visits', [
            'id' => $visit->id,
            'visit_date' => '2026-06-16 00:00:00',
            'diagnosis' => 'Updated diagnosis',
            'updated_by' => $doctor->id,
        ]);

        $deleteResponse = $this->delete(route('patients.card.visits.destroy', [
            'patientId' => $patient->id,
            'visitId' => $visit->id,
        ]));

        $deleteResponse->assertRedirect(route('patients.card.show', ['patientId' => $patient->id]));
        $this->assertSoftDeleted('patient_card_visits', ['id' => $visit->id]);
    }

    public function test_receptionist_can_view_card_but_cannot_modify_medical_visits(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $this->get(route('patients.card.show', ['patientId' => $patient->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.can_manage_visits', false)
            );

        $this->post(route('patients.card.visits.store', ['patientId' => $patient->id]), [
            'visit_date' => '2026-06-15',
            'diagnosis' => 'Not allowed',
        ])->assertForbidden();

        $this->assertDatabaseMissing('patient_card_visits', [
            'patient_id' => $patient->id,
            'diagnosis' => 'Not allowed',
        ]);
    }

    public function test_patient_card_is_scoped_to_authenticated_users_clinic_for_doctor(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $doctor = $this->authenticateForClinic($clinic, 'doctor');

        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);

        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $this->get(route('patients.card.show', ['patientId' => $otherPatient->id]))
            ->assertNotFound();
    }

    public function test_clinic_admin_can_view_patient_card_from_any_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $this->get(route('patients.card.show', ['patientId' => $otherPatient->id]))
            ->assertOk();
    }

    public function test_receptionist_can_view_patient_card_from_any_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'receptionist');
        $otherPatient = Patient::factory()->create(['clinic_id' => $otherClinic->id]);

        $this->get(route('patients.card.show', ['patientId' => $otherPatient->id]))
            ->assertOk();
    }

    public function test_patient_card_pdf_can_be_exported(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic, 'clinic_admin');
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        PatientCardVisit::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'visit_date' => '2026-06-15',
        ]);

        $response = $this->get(route('patients.card.pdf', ['patientId' => $patient->id]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName): User
    {
        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }
}
