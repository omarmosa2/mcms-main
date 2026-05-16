<?php

namespace Database\Seeders;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Actions\Rbac\SyncClinicRbacAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Seeder;

class MvpSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->firstOrCreate(
            ['code' => 'MVP001'],
            [
                'name' => 'Main MVP Clinic',
                'legal_name' => 'Main MVP Clinic LLC',
                'timezone' => 'Asia/Riyadh',
                'currency' => 'SAR',
                'phone' => '000-000-0000',
                'email' => 'clinic@example.com',
                'address' => 'Localhost',
                'is_active' => true,
            ],
        );

        app(SyncClinicRbacAction::class)->handle($clinic->id);

        $admin = $this->seedUser(
            clinic: $clinic,
            name: 'Demo Admin',
            email: 'demo.admin@example.com',
            roleName: 'clinic_admin',
            assignedBy: null,
        );

        $doctor = $this->seedUser(
            clinic: $clinic,
            name: 'Demo Doctor',
            email: 'demo.doctor@example.com',
            roleName: 'doctor',
            assignedBy: $admin->id,
        );
        $receptionist = $this->seedUser(
            clinic: $clinic,
            name: 'Demo Receptionist',
            email: 'demo.receptionist@example.com',
            roleName: 'receptionist',
            assignedBy: $admin->id,
        );
        $accountant = $this->seedUser(
            clinic: $clinic,
            name: 'Demo Accountant',
            email: 'demo.accountant@example.com',
            roleName: 'accountant',
            assignedBy: $admin->id,
        );

        $patient = Patient::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'file_number' => 'PT-MVP-0001',
            ],
            [
                'first_name' => 'Maha',
                'last_name' => 'Ali',
                'date_of_birth' => '1990-01-01',
                'gender' => 'female',
                'phone' => '+966500000001',
                'email' => 'patient.mvp@example.com',
                'national_id' => '1234567890',
                'emergency_contact_name' => 'Alaa Ali',
                'emergency_contact_phone' => '+966500000002',
                'notes' => 'MVP seed patient',
            ],
        );

        $appointment = Appointment::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'appointment_number' => 'APT-MVP-0001',
            ],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'created_by' => $receptionist->id,
                'scheduled_for' => now()->addDay(),
                'duration_minutes' => 30,
                'status' => Appointment::STATUS_ARRIVED,
                'arrived_at' => now()->subMinutes(25),
                'completed_at' => null,
                'canceled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Seeded appointment',
            ],
        );

        $queueEntry = QueueEntry::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'queue_date' => now()->toDateString(),
                'queue_number' => 1,
            ],
            [
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'assigned_doctor_id' => $doctor->id,
                'called_by' => $receptionist->id,
                'priority' => 0,
                'status' => QueueEntry::STATUS_IN_SERVICE,
                'checked_in_at' => now()->subMinutes(30),
                'called_at' => now()->subMinutes(25),
                'started_at' => now()->subMinutes(20),
                'completed_at' => null,
                'notes' => 'Seeded queue entry',
            ],
        );

        $visit = Visit::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'visit_number' => 'VIS-MVP-0001',
            ],
            [
                'queue_entry_id' => $queueEntry->id,
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'status' => Visit::STATUS_STARTED,
                'started_at' => now()->subMinutes(20),
                'in_progress_at' => now()->subMinutes(15),
                'completed_at' => null,
                'chief_complaint' => 'General check-up',
                'clinical_notes' => 'Stable condition',
                'diagnosis_notes' => 'Initial diagnosis pending labs',
                'treatment_plan' => 'Hydration and follow-up',
            ],
        );

        $invoice = Invoice::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'invoice_number' => 'INV-MVP-0001',
            ],
            [
                'patient_id' => $patient->id,
                'visit_id' => $visit->id,
                'appointment_id' => $appointment->id,
                'issued_by' => $accountant->id,
                'status' => Invoice::STATUS_PARTIALLY_PAID,
                'issued_at' => now()->subMinutes(10),
                'due_at' => now()->addDays(14)->toDateString(),
                'subtotal_amount' => 100,
                'discount_amount' => 10,
                'tax_amount' => 5,
                'total_amount' => 95,
                'paid_amount' => 40,
                'balance_amount' => 55,
                'notes' => 'Seeded invoice',
            ],
        );

        InvoiceItem::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'invoice_id' => $invoice->id,
                'description' => 'General consultation',
            ],
            [
                'service_code' => 'SRV-CONS-001',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 10,
                'tax_amount' => 5,
                'line_total' => 95,
            ],
        );

        Payment::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'invoice_id' => $invoice->id,
                'payment_reference' => 'PAY-MVP-0001',
            ],
            [
                'received_by' => $accountant->id,
                'method' => 'cash',
                'status' => Payment::STATUS_RECORDED,
                'amount' => 40,
                'refund_amount' => 0,
                'paid_at' => now()->subMinutes(5),
                'notes' => 'Seeded payment',
            ],
        );

        AuditLog::query()->firstOrCreate(
            [
                'clinic_id' => $clinic->id,
                'user_id' => $admin->id,
                'action' => 'seed.mvp.bootstrap',
                'auditable_type' => Invoice::class,
                'auditable_id' => $invoice->id,
            ],
            [
                'old_values' => null,
                'new_values' => [
                    'invoice_id' => $invoice->id,
                    'patient_id' => $patient->id,
                ],
                'metadata' => [
                    'source' => 'mvp_seeder',
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'DatabaseSeeder',
                'occurred_at' => now(),
            ],
        );
    }

    private function seedUser(
        Clinic $clinic,
        string $name,
        string $email,
        string $roleName,
        ?int $assignedBy,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'clinic_id' => $clinic->id,
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        app(AssignUserRoleAction::class)->handle($user, $roleName, $assignedBy);

        return $user;
    }
}
