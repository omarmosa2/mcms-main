<?php

namespace Tests\Feature\Financial;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinancialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_index_returns_rows_with_clinic_name(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create(['name' => 'Test Clinic']);
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'scheduled_for' => Carbon::parse('2026-06-28 10:00:00'),
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 200,
            'appointment_type' => 'first_visit',
        ]);

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 200,
            'paid_amount' => 0,
            'balance_amount' => 200,
        ]);

        $response = $this->getJson(route('financial.index'));

        $response->assertOk();
        $response->assertJsonPath('financial_rows.0.clinic_name', 'Test Clinic');
        $response->assertJsonPath('financial_rows.0.patient_name', 'Ahmed Ali');
        $response->assertJsonPath('financial_rows.0.cost', 200);
        $response->assertJsonPath('financial_rows.0.payment_status', 'unpaid');
    }

    public function test_financial_index_filters_by_doctor(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor1 = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Doctor One']);
        $doctor2 = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Doctor Two']);

        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $appointment1 = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor1->id,
            'scheduled_for' => Carbon::parse('2026-06-28 10:00:00'),
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 100,
        ]);

        $appointment2 = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor2->id,
            'scheduled_for' => Carbon::parse('2026-06-28 11:00:00'),
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 150,
        ]);

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment1->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment2->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 150,
            'paid_amount' => 0,
            'balance_amount' => 150,
        ]);

        $response = $this->getJson(route('financial.index', ['doctor_id' => $doctor1->id]));

        $response->assertOk();
        $response->assertJsonCount(1, 'financial_rows');
        $response->assertJsonPath('financial_rows.0.doctor_name', 'Doctor One');
    }

    public function test_financial_index_filters_by_payment_status(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);

        $paidAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'scheduled_for' => Carbon::parse('2026-06-28 10:00:00'),
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 100,
        ]);

        $unpaidAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'scheduled_for' => Carbon::parse('2026-06-28 11:00:00'),
            'status' => Appointment::STATUS_SCHEDULED,
            'cost' => 200,
        ]);

        $paidInvoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $paidAppointment->id,
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 100,
            'paid_amount' => 100,
            'balance_amount' => 0,
        ]);

        Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $unpaidAppointment->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 200,
            'paid_amount' => 0,
            'balance_amount' => 200,
        ]);

        Payment::factory()->create([
            'clinic_id' => $clinic->id,
            'invoice_id' => $paidInvoice->id,
            'status' => Payment::STATUS_RECORDED,
            'amount' => 100,
            'refund_amount' => 0,
            'method' => 'cash',
        ]);

        $response = $this->getJson(route('financial.index', ['status' => 'paid']));

        $response->assertOk();
        $response->assertJsonCount(1, 'financial_rows');
        $response->assertJsonPath('financial_rows.0.payment_status', 'paid');
        $response->assertJsonPath('summaries.paid_count', 1);
    }

    public function test_financial_index_returns_doctors_and_patients_dropdowns(): void
    {
        $clinic = Clinic::factory()->create();
        $this->authenticateForClinic($clinic);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Dr. Test']);
        DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
        ]);

        $response = $this->getJson(route('financial.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'doctors' => [['id', 'name']],
            'patients' => [['id', 'full_name']],
        ]);
    }

    public function test_appointment_with_cost_creates_invoice_automatically(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);
        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);
        $this->setClinicWorkingHours($clinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 300,
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();

        $appointment = Appointment::query()->latest()->first();

        $this->assertDatabaseHas('invoices', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 300,
            'paid_amount' => 0,
            'balance_amount' => 300,
        ]);

        $invoice = Invoice::query()->where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'clinic_id' => $clinic->id,
        ]);
    }

    public function test_appointment_with_zero_cost_does_not_create_invoice(): void
    {
        Carbon::setTestNow('2026-06-28 08:00:00');

        $clinic = Clinic::factory()->create();
        $user = $this->authenticateForClinic($clinic);
        $patient = Patient::factory()->create(['clinic_id' => $clinic->id]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        app(AssignUserRoleAction::class)->handle($doctor, 'doctor', $user->id);
        $doctorProfile = DoctorProfile::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctor->id,
            'is_active' => true,
        ]);
        $this->setClinicWorkingHours($clinic, [
            Carbon::MONDAY => ['start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        DoctorSchedule::query()->create([
            'clinic_id' => $clinic->id,
            'doctor_profile_id' => $doctorProfile->id,
            'day_of_week' => Carbon::MONDAY,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_for' => '2026-06-29T11:00:00',
            'duration_minutes' => 30,
            'appointment_type' => 'first_visit',
            'cost' => 0,
        ];

        $response = $this->postJson(route('appointments.store'), $payload);

        $response->assertCreated();

        $appointment = Appointment::query()->latest()->first();

        $this->assertDatabaseMissing('invoices', [
            'appointment_id' => $appointment->id,
        ]);
    }

    private function authenticateForClinic(Clinic $clinic, string $roleName = 'admin'): User
    {
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
        ]);

        app(AssignUserRoleAction::class)->handle($user, $roleName);

        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<int, array{start_time: string, end_time: string}>  $activeDays
     */
    private function setClinicWorkingHours(Clinic $clinic, array $activeDays): void
    {
        foreach (ClinicWorkingHour::DAYS as $day) {
            $hours = $activeDays[$day] ?? null;

            ClinicWorkingHour::query()->create([
                'clinic_id' => $clinic->id,
                'day_of_week' => $day,
                'is_active' => $hours !== null,
                'start_time' => $hours['start_time'] ?? null,
                'end_time' => $hours['end_time'] ?? null,
            ]);
        }
    }
}
