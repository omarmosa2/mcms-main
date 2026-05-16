<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountRateType;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AuditLog;
use App\Models\AuthAttemptLog;
use App\Models\BrandingSetting;
use App\Models\Cashbox;
use App\Models\Clinic;
use App\Models\ComplianceRun;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\DrugBatch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalIntegration;
use App\Models\Installment;
use App\Models\InventoryAlert;
use App\Models\InventoryReturn;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabTestTemplate;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientChronicCondition;
use App\Models\PatientMedication;
use App\Models\PatientPortalToken;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PharmacyDispense;
use App\Models\PharmacyDispenseItem;
use App\Models\PharmacyDrug;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QueueEntry;
use App\Models\RadiologyImage;
use App\Models\RadiologyOrder;
use App\Models\RadiologyReport;
use App\Models\RadiologyStudyType;
use App\Models\Role;
use App\Models\Salary;
use App\Models\SecurityPolicy;
use App\Models\SensitiveAccessLog;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\Visit;
use App\Models\VisitDiagnosis;
use App\Models\VisitVitalSign;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FullSystemSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = Clinic::all();

        if ($clinics->isEmpty()) {
            $this->command->error('No clinics found. Run MvpSeeder first.');

            return;
        }

        foreach ($clinics as $clinic) {
            $this->seedClinicFullData($clinic);
        }
    }

    private function seedClinicFullData(Clinic $clinic): void
    {
        $this->command->info("Seeding full data for clinic: {$clinic->name}");

        $users = User::where('clinic_id', $clinic->id)->get();
        $patients = $this->seedCorePatients($clinic, $users);
        $appointments = $this->seedCoreAppointments($clinic, $patients, $users);
        $queueEntries = $this->seedCoreQueueEntries($clinic, $patients, $appointments, $users);
        $visits = $this->seedCoreVisits($clinic, $queueEntries, $appointments, $users);
        $invoices = $this->seedCoreInvoices($clinic, $visits, $appointments, $users);
        $this->seedCorePayments($clinic, $invoices, $users);
        $this->seedCoreAuditLogs($clinic, $users, $patients, $appointments, $queueEntries, $visits, $invoices);

        $departments = $this->seedDepartments($clinic, $users);
        $this->seedDoctorProfiles($clinic, $users, $departments);
        $this->seedPatientMedicalRecords($clinic, $patients, $users);
        $this->seedAppointmentReminders($clinic, $appointments);
        $this->seedVisitClinicalData($clinic, $visits, $patients, $users);
        $this->seedPharmacy($clinic, $visits, $patients, $users);
        $this->seedLab($clinic, $visits, $patients, $users);
        $this->seedRadiology($clinic, $visits, $patients, $users);
        $this->seedFinancial($clinic, $users, $invoices);
        $this->seedAccounting($clinic, $users);
        $this->seedSuppliersAndPurchasing($clinic, $users);
        $this->seedWorkflows($clinic, $users);
        $this->seedPaymentPlans($clinic, $invoices, $users);
        $this->seedSecurityAndCompliance($clinic, $users, $patients);
        $this->seedPatientPortal($clinic, $patients, $users);
        $this->seedExternalIntegrations($clinic, $users);

        $this->command->info("Completed seeding for clinic: {$clinic->name}");
    }

    private function seedCorePatients(Clinic $clinic, EloquentCollection $users): EloquentCollection
    {
        $existingCount = Patient::where('clinic_id', $clinic->id)->count();
        $targetCount = 50;

        if ($existingCount >= $targetCount) {
            return Patient::where('clinic_id', $clinic->id)->get();
        }

        $firstNames = ['Ahmed', 'Mohammed', 'Fatima', 'Sara', 'Omar', 'Layla', 'Hassan', 'Nour', 'Khalid', 'Maryam', 'Youssef', 'Aisha', 'Ali', 'Zainab', 'Ibrahim', 'Huda', 'Abdullah', 'Rania', 'Salem', 'Dina', 'Tariq', 'Salma', 'Faisal', 'Lina', 'Majid', 'Jana', 'Raed', 'Mona', 'Waleed', 'Rana', 'Samer', 'Hala', 'Bilal', 'Nada', 'Hamza', 'Yasmin', 'Adel', 'Sonia', 'Kamal', 'Reem', 'Nabil', 'Amira', 'Rami', 'Lama', 'Fadi', 'Dana', 'Saad', 'Maha', 'Anas', 'Haneen'];
        $lastNames = ['Al-Ahmad', 'Al-Hassan', 'Al-Mohammed', 'Al-Rashid', 'Al-Omar', 'Al-Fahad', 'Al-Salem', 'Al-Ibrahim', 'Al-Khalid', 'Al-Abdullah', 'Al-Saud', 'Al-Qahtani', 'Al-Dosari', 'Al-Shamrani', 'Al-Ghamdi', 'Al-Harbi', 'Al-Otaibi', 'Al-Mutairi', 'Al-Subaie', 'Al-Zahrani', 'Al-Balawi', 'Al-Juhani', 'Al-Maliki', 'Al-Ruwaili', 'Al-Shammari'];
        $genders = ['male', 'female'];

        $created = new EloquentCollection;

        for ($i = $existingCount; $i < $targetCount; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $gender = $genders[array_rand($genders)];
            $fileNumber = 'MRN-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
            $dob = CarbonImmutable::instance(fake()->dateTimeBetween('-70 years', '-5 years'));

            $created->push(Patient::create([
                'clinic_id' => $clinic->id,
                'file_number' => $fileNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'date_of_birth' => $dob->toDateString(),
                'gender' => $gender,
                'phone' => '+9665'.fake()->numerify('########'),
                'email' => fake()->optional()->safeEmail(),
                'national_id' => fake()->optional()->numerify('##########'),
                'emergency_contact_name' => $firstNames[array_rand($firstNames)].' '.$lastName,
                'emergency_contact_phone' => '+9665'.fake()->numerify('########'),
                'notes' => fake()->optional()->sentence(),
            ]));
        }

        return Patient::where('clinic_id', $clinic->id)->get();
    }

    private function seedCoreAppointments(Clinic $clinic, EloquentCollection $patients, EloquentCollection $users): EloquentCollection
    {
        $existingCount = Appointment::where('clinic_id', $clinic->id)->count();
        $targetCount = 80;

        if ($existingCount >= $targetCount) {
            return Appointment::where('clinic_id', $clinic->id)->get();
        }

        $patientIds = $patients->pluck('id')->all();
        $doctorIds = User::where('clinic_id', $clinic->id)
            ->whereHas('roles', function ($q) use ($clinic) {
                $q->where('roles.clinic_id', $clinic->id)->where('roles.name', 'doctor');
            })->pluck('id')->all();

        if (empty($doctorIds)) {
            $doctorIds = $users->pluck('id')->all();
        }

        $allStaffIds = $users->pluck('id')->all();

        $statuses = [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_ARRIVED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELED,
            Appointment::STATUS_NO_SHOW,
        ];

        $created = new EloquentCollection;

        for ($i = $existingCount; $i < $targetCount; $i++) {
            $status = Arr::random($statuses);
            $scheduledFor = match ($status) {
                Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED => CarbonImmutable::instance(fake()->dateTimeBetween('-2 days', '+20 days')),
                Appointment::STATUS_ARRIVED => CarbonImmutable::instance(fake()->dateTimeBetween('-2 days', 'now')),
                default => CarbonImmutable::instance(fake()->dateTimeBetween('-25 days', '-1 day')),
            };

            $appointmentNumber = 'APT-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);

            $created->push(Appointment::create([
                'clinic_id' => $clinic->id,
                'patient_id' => Arr::random($patientIds),
                'doctor_id' => Arr::random($doctorIds),
                'created_by' => Arr::random($users->pluck('id')->all()),
                'appointment_number' => $appointmentNumber,
                'scheduled_for' => $scheduledFor,
                'duration_minutes' => Arr::random([15, 20, 30, 45, 60]),
                'status' => $status,
                'arrived_at' => in_array($status, [Appointment::STATUS_ARRIVED, Appointment::STATUS_COMPLETED]) ? $scheduledFor->addMinutes(fake()->numberBetween(-5, 15)) : null,
                'completed_at' => $status === Appointment::STATUS_COMPLETED ? $scheduledFor->addMinutes(fake()->numberBetween(30, 90)) : null,
                'canceled_at' => $status === Appointment::STATUS_CANCELED ? $scheduledFor->subHours(fake()->numberBetween(1, 24)) : null,
                'cancel_reason' => $status === Appointment::STATUS_CANCELED ? Arr::random(['Patient requested', 'Doctor unavailable', 'Emergency']) : null,
                'notes' => fake()->optional()->sentence(),
            ]));
        }

        return Appointment::where('clinic_id', $clinic->id)->get();
    }

    private function seedCoreQueueEntries(Clinic $clinic, EloquentCollection $patients, EloquentCollection $appointments, EloquentCollection $users): EloquentCollection
    {
        $existingCount = QueueEntry::where('clinic_id', $clinic->id)->count();
        $targetCount = 60;

        if ($existingCount >= $targetCount) {
            return QueueEntry::where('clinic_id', $clinic->id)->get();
        }

        $patientIds = $patients->pluck('id')->all();
        $staffIds = $users->pluck('id')->all();
        $doctorIds = User::where('clinic_id', $clinic->id)
            ->whereHas('roles', function ($q) use ($clinic) {
                $q->where('roles.clinic_id', $clinic->id)->where('roles.name', 'doctor');
            })->pluck('id')->all();
        if (empty($doctorIds)) {
            $doctorIds = $staffIds;
        }

        $statuses = [
            QueueEntry::STATUS_WAITING,
            QueueEntry::STATUS_WAITING,
            QueueEntry::STATUS_CALLED,
            QueueEntry::STATUS_IN_SERVICE,
            QueueEntry::STATUS_COMPLETED,
            QueueEntry::STATUS_COMPLETED,
            QueueEntry::STATUS_SKIPPED,
            QueueEntry::STATUS_CANCELED,
        ];

        $created = new EloquentCollection;

        for ($i = $existingCount; $i < $targetCount; $i++) {
            $status = Arr::random($statuses);
            $queueDate = CarbonImmutable::instance(fake()->dateTimeBetween('-15 days', 'now'))->toDateString();
            $checkedInAt = CarbonImmutable::parse($queueDate)->setTime(fake()->numberBetween(8, 16), fake()->randomElement([0, 15, 30, 45]));

            $linkedAppointment = $appointments->where('status', '!=', Appointment::STATUS_CANCELED)->random();

            $calledAt = in_array($status, [QueueEntry::STATUS_CALLED, QueueEntry::STATUS_IN_SERVICE, QueueEntry::STATUS_COMPLETED, QueueEntry::STATUS_SKIPPED])
                ? $checkedInAt->addMinutes(fake()->numberBetween(5, 20)) : null;
            $startedAt = in_array($status, [QueueEntry::STATUS_IN_SERVICE, QueueEntry::STATUS_COMPLETED])
                ? $checkedInAt->addMinutes(fake()->numberBetween(15, 35)) : null;
            $completedAt = in_array($status, [QueueEntry::STATUS_COMPLETED, QueueEntry::STATUS_SKIPPED, QueueEntry::STATUS_CANCELED])
                ? $checkedInAt->addMinutes(fake()->numberBetween(40, 90)) : null;

            $created->push(QueueEntry::create([
                'clinic_id' => $clinic->id,
                'appointment_id' => $linkedAppointment?->id,
                'patient_id' => $linkedAppointment?->patient_id ?? Arr::random($patientIds),
                'assigned_doctor_id' => Arr::random($doctorIds),
                'called_by' => fake()->boolean(70) ? Arr::random($staffIds) : null,
                'queue_date' => $queueDate,
                'queue_number' => $i + 1,
                'priority' => fake()->numberBetween(0, 3),
                'status' => $status,
                'checked_in_at' => $checkedInAt,
                'called_at' => $calledAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'notes' => fake()->optional()->sentence(),
            ]));
        }

        return QueueEntry::where('clinic_id', $clinic->id)->get();
    }

    private function seedCoreVisits(Clinic $clinic, EloquentCollection $queueEntries, EloquentCollection $appointments, EloquentCollection $users): EloquentCollection
    {
        $existingCount = Visit::where('clinic_id', $clinic->id)->count();
        $targetCount = 40;

        if ($existingCount >= $targetCount) {
            return Visit::where('clinic_id', $clinic->id)->get();
        }

        $existingQueueIds = Visit::where('clinic_id', $clinic->id)->whereNotNull('queue_entry_id')->pluck('queue_entry_id')->all();
        $eligibleQueues = $queueEntries->whereIn('status', [QueueEntry::STATUS_IN_SERVICE, QueueEntry::STATUS_COMPLETED])
            ->whereNotIn('id', $existingQueueIds)->values();
        $staffIds = $users->pluck('id')->all();
        $usedQueueIds = [];

        $statuses = [Visit::STATUS_STARTED, Visit::STATUS_IN_PROGRESS, Visit::STATUS_COMPLETED];

        $created = new EloquentCollection;

        for ($i = $existingCount; $i < $targetCount; $i++) {
            $availableQueues = $eligibleQueues->whereNotIn('id', $usedQueueIds);

            if ($availableQueues->isEmpty()) {
                break;
            }

            $queueEntry = $availableQueues->random();
            $usedQueueIds[] = $queueEntry->id;
            $status = Arr::random($statuses);
            $startedAt = $queueEntry->started_at ?? CarbonImmutable::instance(fake()->dateTimeBetween('-15 days', 'now'));

            $created->push(Visit::create([
                'clinic_id' => $clinic->id,
                'queue_entry_id' => $queueEntry->id,
                'appointment_id' => $queueEntry->appointment_id,
                'patient_id' => $queueEntry->patient_id,
                'doctor_id' => $queueEntry->assigned_doctor_id ?? Arr::random($staffIds),
                'visit_number' => 'VIS-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'status' => $status,
                'started_at' => $startedAt,
                'in_progress_at' => in_array($status, [Visit::STATUS_IN_PROGRESS, Visit::STATUS_COMPLETED]) ? $startedAt->addMinutes(fake()->numberBetween(5, 15)) : null,
                'completed_at' => $status === Visit::STATUS_COMPLETED ? $startedAt->addMinutes(fake()->numberBetween(30, 90)) : null,
                'chief_complaint' => fake()->optional()->sentence(),
                'clinical_notes' => fake()->optional()->paragraph(),
                'diagnosis_notes' => fake()->optional()->sentence(),
                'treatment_plan' => fake()->optional()->sentence(),
            ]));
        }

        return Visit::where('clinic_id', $clinic->id)->get();
    }

    private function seedCoreInvoices(Clinic $clinic, EloquentCollection $visits, EloquentCollection $appointments, EloquentCollection $users): EloquentCollection
    {
        $existingCount = Invoice::where('clinic_id', $clinic->id)->count();
        $targetCount = 35;

        if ($existingCount >= $targetCount) {
            return Invoice::where('clinic_id', $clinic->id)->get();
        }

        $visitInvoices = $visits->shuffle()->take(min($visits->count(), 25));
        $created = new EloquentCollection;

        foreach ($visitInvoices as $index => $visit) {
            $i = $existingCount + $index;
            $issuedAt = CarbonImmutable::instance(fake()->dateTimeBetween('-20 days', 'now'));
            $subtotal = fake()->randomFloat(2, 100, 2000);
            $discount = round($subtotal * fake()->randomFloat(2, 0, 0.15), 2);
            $tax = round(($subtotal - $discount) * 0.15, 2);
            $total = round($subtotal - $discount + $tax, 2);

            $created->push(Invoice::create([
                'clinic_id' => $clinic->id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'appointment_id' => $visit->appointment_id,
                'issued_by' => Arr::random($users->pluck('id')->all()),
                'invoice_number' => 'INV-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'status' => Invoice::STATUS_ISSUED,
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->addDays(fake()->numberBetween(7, 30)),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'balance_amount' => $total,
                'notes' => fake()->optional()->sentence(),
            ]));
        }

        return Invoice::where('clinic_id', $clinic->id)->get();
    }

    private function seedCorePayments(Clinic $clinic, EloquentCollection $invoices, EloquentCollection $users): void
    {
        foreach ($invoices as $invoice) {
            $total = (float) $invoice->total_amount;
            if ($total <= 0) {
                continue;
            }

            $mode = Arr::random(['none', 'partial', 'partial', 'full', 'full', 'full']);

            if ($mode === 'full') {
                Payment::create([
                    'clinic_id' => $clinic->id,
                    'invoice_id' => $invoice->id,
                    'received_by' => Arr::random($users->pluck('id')->all()),
                    'method' => Arr::random(['cash', 'card', 'bank_transfer', 'insurance']),
                    'status' => Payment::STATUS_RECORDED,
                    'amount' => $total,
                    'refund_amount' => 0,
                    'paid_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-15 days', 'now')),
                    'notes' => 'Full payment',
                ]);
                $invoice->updateQuietly(['paid_amount' => $total, 'balance_amount' => 0, 'status' => Invoice::STATUS_PAID]);
            } elseif ($mode === 'partial') {
                $amount = round($total * fake()->randomFloat(2, 0.3, 0.7), 2);
                Payment::create([
                    'clinic_id' => $clinic->id,
                    'invoice_id' => $invoice->id,
                    'received_by' => Arr::random($users->pluck('id')->all()),
                    'method' => Arr::random(['cash', 'card', 'bank_transfer']),
                    'status' => Payment::STATUS_RECORDED,
                    'amount' => $amount,
                    'refund_amount' => 0,
                    'paid_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-15 days', 'now')),
                    'notes' => 'Partial payment',
                ]);
                $invoice->updateQuietly(['paid_amount' => $amount, 'balance_amount' => round($total - $amount, 2), 'status' => Invoice::STATUS_PARTIALLY_PAID]);
            }
        }
    }

    private function seedCoreAuditLogs(Clinic $clinic, EloquentCollection $users, EloquentCollection $patients, EloquentCollection $appointments, EloquentCollection $queueEntries, EloquentCollection $visits, EloquentCollection $invoices): void
    {
        $existingCount = AuditLog::where('clinic_id', $clinic->id)->count();
        $targetCount = 80;

        if ($existingCount >= $targetCount) {
            return;
        }

        $auditableTypes = [
            ['type' => Patient::class, 'ids' => $patients->pluck('id')->all()],
            ['type' => Appointment::class, 'ids' => $appointments->pluck('id')->all()],
            ['type' => QueueEntry::class, 'ids' => $queueEntries->pluck('id')->all()],
            ['type' => Visit::class, 'ids' => $visits->pluck('id')->all()],
            ['type' => Invoice::class, 'ids' => $invoices->pluck('id')->all()],
        ];

        $actions = ['created', 'updated', 'status_changed', 'reviewed'];

        for ($i = $existingCount; $i < $targetCount; $i++) {
            $type = Arr::random($auditableTypes);
            if (empty($type['ids'])) {
                continue;
            }

            AuditLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => Arr::random($users->pluck('id')->all()),
                'action' => Arr::random($actions),
                'auditable_type' => $type['type'],
                'auditable_id' => Arr::random($type['ids']),
                'old_values' => null,
                'new_values' => ['action' => 'seeded'],
                'metadata' => ['source' => 'full_system_seeder'],
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'occurred_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')),
            ]);
        }
    }

    private function seedDepartments(Clinic $clinic, EloquentCollection $users): EloquentCollection
    {
        $departmentNames = [
            ['name' => 'General Medicine', 'code' => 'GEN-MED'],
            ['name' => 'Cardiology', 'code' => 'CARD'],
            ['name' => 'Dermatology', 'code' => 'DERM'],
            ['name' => 'Pediatrics', 'code' => 'PED'],
            ['name' => 'Orthopedics', 'code' => 'ORTH'],
            ['name' => 'Neurology', 'code' => 'NEUR'],
            ['name' => 'Ophthalmology', 'code' => 'OPHT'],
            ['name' => 'ENT', 'code' => 'ENT'],
            ['name' => 'Emergency', 'code' => 'ER'],
            ['name' => 'Radiology', 'code' => 'RAD'],
            ['name' => 'Laboratory', 'code' => 'LAB'],
            ['name' => 'Pharmacy', 'code' => 'PHARM'],
        ];

        $adminUser = $users->first();
        $departments = new EloquentCollection;

        foreach ($departmentNames as $index => $dept) {
            $departments->push(Department::updateOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $dept['code']],
                [
                    'name' => $dept['name'],
                    'description' => "{$dept['name']} Department",
                    'is_active' => $index < 10,
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ],
            ));
        }

        return $departments;
    }

    private function seedDoctorProfiles(Clinic $clinic, EloquentCollection $users, EloquentCollection $departments): void
    {
        $specialties = [
            'Family Medicine', 'Internal Medicine', 'Pediatrics',
            'Dermatology', 'Cardiology', 'Orthopedics',
            'Neurology', 'Ophthalmology', 'ENT', 'Emergency Medicine',
        ];

        $doctorRole = Role::where('clinic_id', $clinic->id)->where('name', 'doctor')->first();

        if (! $doctorRole) {
            return;
        }

        $doctorUserIds = DB::table('role_user')
            ->where('clinic_id', $clinic->id)
            ->where('role_id', $doctorRole->id)
            ->pluck('user_id')
            ->all();

        $doctorUsers = $users->whereIn('id', $doctorUserIds);

        if ($doctorUsers->isEmpty()) {
            return;
        }

        foreach ($doctorUsers as $index => $doctor) {
            DoctorProfile::updateOrCreate(
                ['clinic_id' => $clinic->id, 'user_id' => $doctor->id],
                [
                    'department_id' => $departments->random()->id,
                    'license_number' => 'LIC-'.strtoupper(Str::random(8)),
                    'specialty' => $specialties[$index % count($specialties)],
                    'consultation_duration_minutes' => Arr::random([15, 20, 30, 45, 60]),
                    'status' => Arr::random([
                        DoctorProfile::STATUS_ACTIVE,
                        DoctorProfile::STATUS_ACTIVE,
                        DoctorProfile::STATUS_ACTIVE,
                        DoctorProfile::STATUS_ON_LEAVE,
                        DoctorProfile::STATUS_INACTIVE,
                    ]),
                    'work_schedule' => [
                        'sunday' => ['09:00-13:00', '17:00-20:00'],
                        'monday' => ['09:00-13:00', '17:00-20:00'],
                        'tuesday' => ['09:00-13:00'],
                        'wednesday' => ['09:00-13:00', '17:00-20:00'],
                        'thursday' => ['09:00-13:00'],
                    ],
                    'bio' => "Experienced {$specialties[$index % count($specialties)]} specialist.",
                ],
            );
        }
    }

    private function seedPatientMedicalRecords(Clinic $clinic, EloquentCollection $patients, EloquentCollection $users): void
    {
        $allergies = [
            'Penicillin', 'Aspirin', 'Sulfa drugs', 'Latex', 'Peanuts',
            'Shellfish', 'Eggs', 'Milk', 'Soy', 'Wheat',
            'Bee stings', 'Dust mites', 'Pollen', 'Pet dander', 'Mold',
            'Iodine contrast', 'Codeine', 'Ibuprofen', 'Morphine', 'Insulin',
        ];

        $medications = [
            'Metformin 500mg', 'Lisinopril 10mg', 'Atorvastatin 20mg',
            'Amlodipine 5mg', 'Omeprazole 20mg', 'Levothyroxine 50mcg',
            'Metoprolol 25mg', 'Losartan 50mg', 'Gabapentin 300mg',
            'Hydrochlorothiazide 25mg', 'Warfarin 5mg', 'Clopidogrel 75mg',
            'Sertraline 50mg', 'Albuterol inhaler', 'Fluticasone nasal spray',
        ];

        $chronicConditions = [
            'Type 2 Diabetes Mellitus', 'Hypertension', 'Hyperlipidemia',
            'Asthma', 'COPD', 'Hypothyroidism', 'Hyperthyroidism',
            'Chronic Kidney Disease', 'Heart Failure', 'Atrial Fibrillation',
            'Coronary Artery Disease', 'Osteoarthritis', 'Rheumatoid Arthritis',
            'Depression', 'Anxiety Disorder', 'GERD', 'Migraine',
            'Epilepsy', 'Psoriasis', 'Anemia',
        ];

        $samplePatients = $patients->take(min(50, $patients->count()));

        foreach ($samplePatients as $patient) {
            $allergyCount = fake()->numberBetween(0, 4);
            $selectedAllergies = collect($allergies)->shuffle()->take($allergyCount);

            foreach ($selectedAllergies as $allergy) {
                PatientAllergy::updateOrCreate(
                    ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'allergy' => $allergy],
                );
            }

            $medCount = fake()->numberBetween(0, 3);
            $selectedMedications = collect($medications)->shuffle()->take($medCount);

            foreach ($selectedMedications as $medication) {
                PatientMedication::updateOrCreate(
                    ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'medication' => $medication],
                );
            }

            $conditionCount = fake()->numberBetween(0, 3);
            $selectedConditions = collect($chronicConditions)->shuffle()->take($conditionCount);

            foreach ($selectedConditions as $condition) {
                PatientChronicCondition::updateOrCreate(
                    ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'condition' => $condition],
                );
            }
        }
    }

    private function seedAppointmentReminders(Clinic $clinic, EloquentCollection $appointments): void
    {
        $eligibleAppointments = $appointments->whereIn('status', [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_NO_SHOW,
        ])->take(60);

        foreach ($eligibleAppointments as $appointment) {
            $channel = Arr::random([
                AppointmentReminder::CHANNEL_SMS,
                AppointmentReminder::CHANNEL_WHATSAPP,
            ]);

            $existing = AppointmentReminder::where('appointment_id', $appointment->id)
                ->where('channel', $channel)
                ->first();

            if ($existing) {
                continue;
            }

            $status = match ($appointment->status) {
                Appointment::STATUS_COMPLETED => Arr::random([
                    AppointmentReminder::STATUS_SENT,
                    AppointmentReminder::STATUS_SENT,
                    AppointmentReminder::STATUS_FAILED,
                ]),
                Appointment::STATUS_NO_SHOW => Arr::random([
                    AppointmentReminder::STATUS_FAILED,
                    AppointmentReminder::STATUS_SKIPPED,
                ]),
                default => Arr::random([
                    AppointmentReminder::STATUS_QUEUED,
                    AppointmentReminder::STATUS_SENT,
                    AppointmentReminder::STATUS_FAILED,
                ]),
            };

            $scheduledFor = CarbonImmutable::parse($appointment->scheduled_for)->subHours(24);

            AppointmentReminder::create([
                'clinic_id' => $clinic->id,
                'appointment_id' => $appointment->id,
                'channel' => $channel,
                'status' => $status,
                'scheduled_for' => $scheduledFor,
                'sent_at' => $status === AppointmentReminder::STATUS_SENT ? $scheduledFor->addMinutes(fake()->numberBetween(1, 30)) : null,
                'failed_at' => $status === AppointmentReminder::STATUS_FAILED ? $scheduledFor->addMinutes(fake()->numberBetween(1, 30)) : null,
                'failure_reason' => $status === AppointmentReminder::STATUS_FAILED ? Arr::random([
                    'Invalid phone number',
                    'SMS gateway timeout',
                    'Rate limit exceeded',
                    'Provider error',
                ]) : null,
                'provider_message_id' => $status === AppointmentReminder::STATUS_SENT ? 'MSG-'.strtoupper(Str::random(10)) : null,
                'metadata' => ['template' => 'appointment_reminder_24h'],
            ]);
        }
    }

    private function seedVisitClinicalData(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users): void
    {
        $completedVisits = $visits->where('status', Visit::STATUS_COMPLETED)->take(40);
        $inProgressVisits = $visits->where('status', Visit::STATUS_IN_PROGRESS)->take(10);
        $sampleVisits = $completedVisits->concat($inProgressVisits);

        $chiefComplaints = [
            'Headache and dizziness', 'Chest pain', 'Abdominal pain',
            'Shortness of breath', 'Back pain', 'Joint pain',
            'Skin rash', 'Fever and chills', 'Cough and sore throat',
            'Nausea and vomiting', 'Fatigue and weakness', 'Vision changes',
            'Ear pain', 'Urinary frequency', 'Weight loss',
        ];

        $icd10Codes = [
            ['code' => 'J06.9', 'title' => 'Acute upper respiratory infection'],
            ['code' => 'I10', 'title' => 'Essential hypertension'],
            ['code' => 'E11.9', 'title' => 'Type 2 diabetes mellitus without complications'],
            ['code' => 'M54.5', 'title' => 'Low back pain'],
            ['code' => 'J45.909', 'title' => 'Unspecified asthma'],
            ['code' => 'K21.0', 'title' => 'GERD with esophagitis'],
            ['code' => 'N39.0', 'title' => 'Urinary tract infection'],
            ['code' => 'L30.9', 'title' => 'Dermatitis, unspecified'],
            ['code' => 'G43.909', 'title' => 'Migraine, unspecified'],
            ['code' => 'F41.1', 'title' => 'Generalized anxiety disorder'],
            ['code' => 'E78.0', 'title' => 'Pure hypercholesterolemia'],
            ['code' => 'M17.9', 'title' => 'Osteoarthritis of knee'],
            ['code' => 'J02.9', 'title' => 'Acute pharyngitis'],
            ['code' => 'R51.9', 'title' => 'Headache, unspecified'],
            ['code' => 'R10.9', 'title' => 'Unspecified abdominal pain'],
        ];

        foreach ($sampleVisits as $visit) {
            $existingVital = VisitVitalSign::where('visit_id', $visit->id)->first();

            if (! $existingVital) {
                $nurseUser = $users->random();

                VisitVitalSign::create([
                    'clinic_id' => $clinic->id,
                    'visit_id' => $visit->id,
                    'patient_id' => $visit->patient_id,
                    'recorded_by' => $nurseUser->id,
                    'systolic_bp' => fake()->numberBetween(90, 180),
                    'diastolic_bp' => fake()->numberBetween(60, 110),
                    'heart_rate' => fake()->numberBetween(55, 110),
                    'respiratory_rate' => fake()->numberBetween(12, 24),
                    'oxygen_saturation' => fake()->numberBetween(92, 100),
                    'temperature_celsius' => fake()->randomFloat(1, 36.0, 39.5),
                    'weight_kg' => fake()->randomFloat(1, 40, 120),
                    'height_cm' => fake()->numberBetween(140, 195),
                    'recorded_at' => $visit->started_at ?? now(),
                    'notes' => fake()->optional()->sentence(),
                ]);
            }

            $diagnosisCount = fake()->numberBetween(1, 3);
            $selectedDiagnoses = collect($icd10Codes)->shuffle()->take($diagnosisCount);
            $existingDiagnoses = VisitDiagnosis::where('visit_id', $visit->id)->pluck('icd10_code')->all();

            foreach ($selectedDiagnoses as $index => $diagnosis) {
                if (in_array($diagnosis['code'], $existingDiagnoses)) {
                    continue;
                }

                VisitDiagnosis::create([
                    'clinic_id' => $clinic->id,
                    'visit_id' => $visit->id,
                    'patient_id' => $visit->patient_id,
                    'diagnosed_by' => $visit->doctor_id ?? $users->random()->id,
                    'icd10_code' => $diagnosis['code'],
                    'diagnosis_title' => $diagnosis['title'],
                    'is_primary' => $index === 0,
                    'notes' => fake()->optional()->sentence(),
                    'diagnosed_at' => $visit->completed_at ?? now(),
                ]);
            }

            $visit->updateQuietly([
                'chief_complaint' => Arr::random($chiefComplaints),
                'clinical_notes' => 'Patient presented with symptoms. Examination conducted. Vital signs recorded.',
                'diagnosis_notes' => 'Diagnosis based on clinical presentation and examination findings.',
                'treatment_plan' => 'Prescribed medication. Follow-up in 2 weeks. Lifestyle modifications advised.',
            ]);
        }
    }

    private function seedPharmacy(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users): void
    {
        $drugs = $this->seedPharmacyDrugs($clinic);
        $this->seedDrugBatches($clinic, $drugs);
        $this->seedPrescriptions($clinic, $visits, $patients, $users, $drugs);
        $this->seedStockAdjustments($clinic, $drugs, $users);
        $this->seedInventoryReturns($clinic, $drugs, $users);
        $this->seedInventoryAlerts($clinic, $drugs, $users);
    }

    private function seedPharmacyDrugs(Clinic $clinic): EloquentCollection
    {
        $drugList = [
            ['trade' => 'Panadol', 'generic' => 'Paracetamol', 'form' => 'tablet', 'strength' => '500mg', 'price' => 500],
            ['trade' => 'Amoxil', 'generic' => 'Amoxicillin', 'form' => 'capsule', 'strength' => '500mg', 'price' => 1200],
            ['trade' => 'Zithromax', 'generic' => 'Azithromycin', 'form' => 'tablet', 'strength' => '250mg', 'price' => 2500],
            ['trade' => 'Voltaren', 'generic' => 'Diclofenac', 'form' => 'tablet', 'strength' => '50mg', 'price' => 800],
            ['trade' => 'Norvasc', 'generic' => 'Amlodipine', 'form' => 'tablet', 'strength' => '5mg', 'price' => 1500],
            ['trade' => 'Glucophage', 'generic' => 'Metformin', 'form' => 'tablet', 'strength' => '500mg', 'price' => 600],
            ['trade' => 'Lipitor', 'generic' => 'Atorvastatin', 'form' => 'tablet', 'strength' => '20mg', 'price' => 3000],
            ['trade' => 'Prilosec', 'generic' => 'Omeprazole', 'form' => 'capsule', 'strength' => '20mg', 'price' => 1800],
            ['trade' => 'Ventolin', 'generic' => 'Albuterol', 'form' => 'syrup', 'strength' => '2mg/5ml', 'price' => 900],
            ['trade' => 'Zoloft', 'generic' => 'Sertraline', 'form' => 'tablet', 'strength' => '50mg', 'price' => 2200],
            ['trade' => 'Lasix', 'generic' => 'Furosemide', 'form' => 'tablet', 'strength' => '40mg', 'price' => 400],
            ['trade' => 'Augmentin', 'generic' => 'Amoxicillin/Clavulanate', 'form' => 'tablet', 'strength' => '625mg', 'price' => 2800],
            ['trade' => 'Cataflam', 'generic' => 'Diclofenac Potassium', 'form' => 'tablet', 'strength' => '50mg', 'price' => 700],
            ['trade' => 'Flagyl', 'generic' => 'Metronidazole', 'form' => 'tablet', 'strength' => '500mg', 'price' => 600],
            ['trade' => 'Ciproxin', 'generic' => 'Ciprofloxacin', 'form' => 'tablet', 'strength' => '500mg', 'price' => 1100],
            ['trade' => 'Insulin Humalog', 'generic' => 'Insulin Lispro', 'form' => 'injection', 'strength' => '100U/ml', 'price' => 4500],
            ['trade' => 'Synthroid', 'generic' => 'Levothyroxine', 'form' => 'tablet', 'strength' => '50mcg', 'price' => 800],
            ['trade' => 'Plavix', 'generic' => 'Clopidogrel', 'form' => 'tablet', 'strength' => '75mg', 'price' => 3500],
            ['trade' => 'Neurontin', 'generic' => 'Gabapentin', 'form' => 'capsule', 'strength' => '300mg', 'price' => 1600],
            ['trade' => 'Cozaar', 'generic' => 'Losartan', 'form' => 'tablet', 'strength' => '50mg', 'price' => 1400],
        ];

        $drugs = new EloquentCollection;

        foreach ($drugList as $index => $drug) {
            $drugs->push(PharmacyDrug::updateOrCreate(
                ['clinic_id' => $clinic->id, 'trade_name' => $drug['trade']],
                [
                    'generic_name' => $drug['generic'],
                    'dosage_form' => $drug['form'],
                    'strength' => $drug['strength'],
                    'supplier_name' => Arr::random(['PharmaCo', 'MedSupply', 'HealthDist', 'DrugWholesale']),
                    'unit_price' => $drug['price'],
                    'min_stock_level' => fake()->numberBetween(10, 50),
                    'current_stock' => fake()->numberBetween(0, 300),
                    'is_active' => $index < 18,
                    'expires_at' => fake()->optional()->dateTimeBetween('+3 months', '+2 years'),
                ],
            ));
        }

        return $drugs;
    }

    private function seedDrugBatches(Clinic $clinic, EloquentCollection $drugs): void
    {
        $activeDrugs = $drugs->where('is_active', true)->take(15);

        foreach ($activeDrugs as $drug) {
            $batchCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $batchCount; $i++) {
                $quantity = fake()->numberBetween(50, 500);
                $batchNumber = 'BATCH-'.strtoupper(Str::random(6));

                DrugBatch::updateOrCreate(
                    ['clinic_id' => $clinic->id, 'pharmacy_drug_id' => $drug->id, 'batch_number' => $batchNumber],
                    [
                        'quantity' => $quantity,
                        'initial_quantity' => $quantity,
                        'expiry_date' => fake()->dateTimeBetween('+1 month', '+3 years'),
                        'received_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-6 months', '-1 day')),
                    ],
                );
            }
        }

        $firstDrug = $drugs->first();
        DrugBatch::updateOrCreate(
            ['clinic_id' => $clinic->id, 'pharmacy_drug_id' => $firstDrug->id, 'batch_number' => 'BATCH-EXPIRED-001'],
            [
                'quantity' => 0,
                'initial_quantity' => 100,
                'expiry_date' => CarbonImmutable::instance(fake()->dateTimeBetween('-6 months', '-1 day')),
                'received_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-2 years', '-1 year')),
            ],
        );

        $secondDrug = $drugs->skip(1)->first() ?? $firstDrug;
        DrugBatch::updateOrCreate(
            ['clinic_id' => $clinic->id, 'pharmacy_drug_id' => $secondDrug->id, 'batch_number' => 'BATCH-NEAREXPIRY-001'],
            [
                'quantity' => 50,
                'initial_quantity' => 200,
                'expiry_date' => CarbonImmutable::instance(fake()->dateTimeBetween('+1 day', '+15 days')),
                'received_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-6 months', '-3 months')),
            ],
        );
    }

    private function seedPrescriptions(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users, EloquentCollection $drugs): void
    {
        $eligibleVisits = $visits->whereIn('status', [
            Visit::STATUS_IN_PROGRESS,
            Visit::STATUS_COMPLETED,
        ])->take(30);

        $frequencies = [
            'Once daily', 'Twice daily', 'Three times daily',
            'Four times daily', 'Every 8 hours', 'Every 12 hours',
            'As needed', 'Before meals', 'After meals', 'At bedtime',
        ];

        $durations = ['3 days', '5 days', '7 days', '10 days', '14 days', '21 days', '30 days', 'Until finished'];

        foreach ($eligibleVisits as $visit) {
            $status = $visit->status === Visit::STATUS_COMPLETED
                ? Arr::random([
                    Prescription::STATUS_ISSUED,
                    Prescription::STATUS_ISSUED,
                    Prescription::STATUS_DISPENSED,
                    Prescription::STATUS_CANCELED,
                ])
                : Prescription::STATUS_DRAFT;

            $prescription = Prescription::create([
                'clinic_id' => $clinic->id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'prescribed_by' => $visit->doctor_id ?? $users->random()->id,
                'prescription_number' => 'RX-'.strtoupper(Str::random(8)),
                'status' => $status,
                'issued_at' => $status !== Prescription::STATUS_DRAFT ? now()->subHours(fake()->numberBetween(1, 48)) : null,
                'dispensed_at' => $status === Prescription::STATUS_DISPENSED ? now()->subHours(fake()->numberBetween(1, 24)) : null,
                'notes' => fake()->optional()->sentence(),
            ]);

            $itemCount = fake()->numberBetween(1, 4);
            $selectedDrugs = $drugs->where('is_active', true)->shuffle()->take($itemCount);

            foreach ($selectedDrugs as $drug) {
                $quantity = fake()->numberBetween(5, 60);

                PrescriptionItem::create([
                    'clinic_id' => $clinic->id,
                    'prescription_id' => $prescription->id,
                    'pharmacy_drug_id' => $drug->id,
                    'medication_name' => "{$drug->trade_name} {$drug->strength}",
                    'dosage' => $drug->strength,
                    'frequency' => Arr::random($frequencies),
                    'duration' => Arr::random($durations),
                    'quantity' => $quantity,
                    'instructions' => "Take {$quantity} {$drug->dosage_form}(s). ".Arr::random($frequencies).' for '.Arr::random($durations).'.',
                ]);
            }

            if ($status === Prescription::STATUS_DISPENSED) {
                $totalAmount = 0;

                foreach ($selectedDrugs as $drug) {
                    $totalAmount += $drug->unit_price * fake()->numberBetween(5, 60);
                }

                $dispense = PharmacyDispense::create([
                    'clinic_id' => $clinic->id,
                    'prescription_id' => $prescription->id,
                    'dispensed_by' => $users->random()->id,
                    'dispensed_at' => $prescription->dispensed_at,
                    'total_amount' => round($totalAmount, 2),
                    'notes' => 'Fully dispensed',
                ]);

                foreach ($prescription->items as $item) {
                    if ($item->pharmacy_drug_id) {
                        PharmacyDispenseItem::create([
                            'clinic_id' => $clinic->id,
                            'pharmacy_dispense_id' => $dispense->id,
                            'prescription_item_id' => $item->id,
                            'pharmacy_drug_id' => $item->pharmacy_drug_id,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->drug?->unit_price ?? 0,
                            'line_total' => ($item->drug?->unit_price ?? 0) * $item->quantity,
                        ]);
                    }
                }
            }
        }
    }

    private function seedStockAdjustments(Clinic $clinic, EloquentCollection $drugs, EloquentCollection $users): void
    {
        $activeDrugs = $drugs->where('is_active', true)->take(10);

        foreach ($activeDrugs as $drug) {
            StockAdjustment::create([
                'clinic_id' => $clinic->id,
                'pharmacy_drug_id' => $drug->id,
                'quantity_change' => fake()->numberBetween(-30, 50),
                'reason' => Arr::random(['count_correction', 'damaged', 'expired', 'received', 'returned', 'other']),
                'adjusted_by' => $users->random()->id,
                'adjusted_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', '-1 day')),
                'notes' => fake()->sentence(),
            ]);
        }
    }

    private function seedInventoryReturns(Clinic $clinic, EloquentCollection $drugs, EloquentCollection $users): void
    {
        $activeDrugs = $drugs->where('is_active', true)->take(5);
        $suppliers = Supplier::where('clinic_id', $clinic->id)->get();

        foreach ($activeDrugs as $drug) {
            InventoryReturn::create([
                'clinic_id' => $clinic->id,
                'pharmacy_drug_id' => $drug->id,
                'quantity' => fake()->numberBetween(5, 30),
                'reason' => Arr::random(['expired', 'damaged', 'wrong_order', 'quality_issue', 'other']),
                'returned_to_supplier' => fake()->boolean(70),
                'supplier_id' => $suppliers->isNotEmpty() ? $suppliers->random()->id : null,
                'returned_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', '-1 day')),
                'notes' => fake()->sentence(),
            ]);
        }
    }

    private function seedInventoryAlerts(Clinic $clinic, EloquentCollection $drugs, EloquentCollection $users): void
    {
        $activeDrugs = $drugs->where('is_active', true)->take(10);

        foreach ($activeDrugs as $drug) {
            $type = Arr::random([
                InventoryAlert::TYPE_LOW_STOCK,
                InventoryAlert::TYPE_NEAR_EXPIRY,
                InventoryAlert::TYPE_EXPIRED,
            ]);

            InventoryAlert::create([
                'clinic_id' => $clinic->id,
                'pharmacy_drug_id' => $drug->id,
                'type' => $type,
                'status' => Arr::random([
                    InventoryAlert::STATUS_OPEN,
                    InventoryAlert::STATUS_OPEN,
                    InventoryAlert::STATUS_RESOLVED,
                ]),
                'severity' => Arr::random([
                    InventoryAlert::SEVERITY_LOW,
                    InventoryAlert::SEVERITY_MEDIUM,
                    InventoryAlert::SEVERITY_HIGH,
                ]),
                'message' => match ($type) {
                    InventoryAlert::TYPE_LOW_STOCK => "Low stock alert for {$drug->trade_name}. Current: {$drug->current_stock}, Min: {$drug->min_stock_level}",
                    InventoryAlert::TYPE_NEAR_EXPIRY => "Drug {$drug->trade_name} expires soon. Check batches.",
                    InventoryAlert::TYPE_EXPIRED => "Expired batch detected for {$drug->trade_name}.",
                },
                'metadata' => ['auto_generated' => true],
                'detected_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-7 days', 'now')),
                'resolved_by' => fake()->boolean(30) ? $users->random()->id : null,
                'resolved_at' => fake()->boolean(30) ? CarbonImmutable::instance(fake()->dateTimeBetween('-3 days', 'now')) : null,
            ]);
        }
    }

    private function seedLab(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users): void
    {
        $this->seedLabTestTemplates($clinic);
        $this->seedLabOrdersAndResults($clinic, $visits, $patients, $users);
    }

    private function seedLabTestTemplates(Clinic $clinic): void
    {
        $templates = [
            ['name' => 'Complete Blood Count (CBC)', 'code' => 'CBC', 'category' => 'hematology', 'unit' => 'cells/uL', 'min' => 4.5, 'max' => 11.0],
            ['name' => 'Fasting Blood Sugar', 'code' => 'FBS', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 70, 'max' => 100],
            ['name' => 'HbA1c', 'code' => 'HBA1C', 'category' => 'chemistry', 'unit' => '%', 'min' => 4.0, 'max' => 5.6],
            ['name' => 'Lipid Profile - Total Cholesterol', 'code' => 'TC', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 0, 'max' => 200],
            ['name' => 'Lipid Profile - LDL', 'code' => 'LDL', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 0, 'max' => 100],
            ['name' => 'Lipid Profile - HDL', 'code' => 'HDL', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 40, 'max' => 60],
            ['name' => 'Triglycerides', 'code' => 'TG', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 0, 'max' => 150],
            ['name' => 'Liver Function - ALT', 'code' => 'ALT', 'category' => 'chemistry', 'unit' => 'U/L', 'min' => 7, 'max' => 56],
            ['name' => 'Liver Function - AST', 'code' => 'AST', 'category' => 'chemistry', 'unit' => 'U/L', 'min' => 10, 'max' => 40],
            ['name' => 'Kidney Function - Creatinine', 'code' => 'CREA', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 0.7, 'max' => 1.3],
            ['name' => 'Kidney Function - Urea', 'code' => 'UREA', 'category' => 'chemistry', 'unit' => 'mg/dL', 'min' => 7, 'max' => 20],
            ['name' => 'Thyroid Stimulating Hormone', 'code' => 'TSH', 'category' => 'immunology', 'unit' => 'mIU/L', 'min' => 0.4, 'max' => 4.0],
            ['name' => 'Urinalysis', 'code' => 'UA', 'category' => 'microbiology', 'unit' => '-', 'min' => 0, 'max' => 0],
            ['name' => 'Blood Culture', 'code' => 'BC', 'category' => 'microbiology', 'unit' => '-', 'min' => 0, 'max' => 0],
            ['name' => 'Vitamin D', 'code' => 'VITD', 'category' => 'immunology', 'unit' => 'ng/mL', 'min' => 30, 'max' => 100],
        ];

        foreach ($templates as $template) {
            LabTestTemplate::updateOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $template['code']],
                [
                    'name' => $template['name'],
                    'category' => $template['category'],
                    'unit' => $template['unit'],
                    'min_reference' => $template['min'],
                    'max_reference' => $template['max'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedLabOrdersAndResults(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users): void
    {
        $eligibleVisits = $visits->take(25);
        $templates = LabTestTemplate::where('clinic_id', $clinic->id)->get();

        $testNames = [
            'CBC', 'Fasting Blood Sugar', 'HbA1c', 'Lipid Profile',
            'Liver Function Test', 'Kidney Function Test', 'TSH',
            'Urinalysis', 'Blood Culture', 'Vitamin D',
        ];

        foreach ($eligibleVisits as $visit) {
            $status = Arr::random([
                LabOrder::STATUS_ORDERED,
                LabOrder::STATUS_ORDERED,
                LabOrder::STATUS_SAMPLE_COLLECTED,
                LabOrder::STATUS_RESULTED,
                LabOrder::STATUS_CANCELED,
            ]);

            $labOrder = LabOrder::create([
                'clinic_id' => $clinic->id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'ordered_by' => $visit->doctor_id ?? $users->random()->id,
                'test_code' => $templates->isNotEmpty() ? $templates->random()->code : 'LAB-'.strtoupper(Str::random(4)),
                'test_name' => Arr::random($testNames),
                'status' => $status,
                'ordered_at' => $visit->started_at ?? now(),
                'notes' => fake()->optional()->sentence(),
            ]);

            if (in_array($status, [LabOrder::STATUS_RESULTED, LabOrder::STATUS_SAMPLE_COLLECTED])) {
                $resultCount = fake()->numberBetween(1, 5);

                for ($i = 0; $i < $resultCount; $i++) {
                    $template = $templates->isNotEmpty() ? $templates->random() : null;
                    $resultValue = $template ? fake()->randomFloat(2, $template->min_reference * 0.5, $template->max_reference * 1.5) : fake()->randomFloat(2, 1, 200);

                    LabResult::create([
                        'clinic_id' => $clinic->id,
                        'lab_order_id' => $labOrder->id,
                        'resulted_by' => $users->random()->id,
                        'result_value' => $resultValue,
                        'unit' => $template?->unit ?? 'mg/dL',
                        'reference_range' => $template ? "{$template->min_reference} - {$template->max_reference}" : 'N/A',
                        'notes' => fake()->optional()->sentence(),
                        'resulted_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')),
                    ]);
                }

                if ($status === LabOrder::STATUS_RESULTED) {
                    $labOrder->updateQuietly(['status' => LabOrder::STATUS_RESULTED]);
                }
            }
        }
    }

    private function seedRadiology(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users): void
    {
        $this->seedRadiologyStudyTypes($clinic);
        $this->seedRadiologyOrdersAndReports($clinic, $visits, $patients, $users);
    }

    private function seedRadiologyStudyTypes(Clinic $clinic): void
    {
        $studyTypes = [
            ['name' => 'Chest X-Ray', 'code' => 'CXR', 'description' => 'Standard chest radiograph', 'contrast' => false],
            ['name' => 'Abdominal X-Ray', 'code' => 'AXR', 'description' => 'Abdominal radiograph', 'contrast' => false],
            ['name' => 'CT Scan Head', 'code' => 'CT-HEAD', 'description' => 'Computed tomography of the head', 'contrast' => false],
            ['name' => 'CT Scan Chest with Contrast', 'code' => 'CT-CHEST-C', 'description' => 'CT chest with IV contrast', 'contrast' => true],
            ['name' => 'CT Scan Abdomen', 'code' => 'CT-ABD', 'description' => 'CT abdomen and pelvis', 'contrast' => false],
            ['name' => 'MRI Brain', 'code' => 'MRI-BRAIN', 'description' => 'Magnetic resonance imaging of brain', 'contrast' => false],
            ['name' => 'MRI Spine', 'code' => 'MRI-SPINE', 'description' => 'Magnetic resonance imaging of spine', 'contrast' => false],
            ['name' => 'Ultrasound Abdomen', 'code' => 'US-ABD', 'description' => 'Abdominal ultrasound', 'contrast' => false],
            ['name' => 'Ultrasound Pelvis', 'code' => 'US-PEL', 'description' => 'Pelvic ultrasound', 'contrast' => false],
            ['name' => 'Mammography', 'code' => 'MAMMO', 'description' => 'Breast mammography', 'contrast' => false],
            ['name' => 'Bone Scan', 'code' => 'BONE', 'description' => 'Nuclear medicine bone scan', 'contrast' => false],
            ['name' => 'Echocardiogram', 'code' => 'ECHO', 'description' => 'Cardiac ultrasound', 'contrast' => false],
        ];

        foreach ($studyTypes as $type) {
            RadiologyStudyType::updateOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'requires_contrast' => $type['contrast'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedRadiologyOrdersAndReports(Clinic $clinic, EloquentCollection $visits, EloquentCollection $patients, EloquentCollection $users): void
    {
        $eligibleVisits = $visits->take(15);
        $studyTypes = RadiologyStudyType::where('clinic_id', $clinic->id)->get();

        $modalities = ['X-Ray', 'CT', 'MRI', 'Ultrasound', 'Mammography', 'Fluoroscopy'];

        foreach ($eligibleVisits as $visit) {
            $studyType = $studyTypes->isNotEmpty() ? $studyTypes->random() : null;
            $status = Arr::random([
                RadiologyOrder::STATUS_ORDERED,
                RadiologyOrder::STATUS_ORDERED,
                RadiologyOrder::STATUS_COMPLETED,
                RadiologyOrder::STATUS_REPORTED,
                RadiologyOrder::STATUS_CANCELED,
            ]);

            $radiologyOrder = RadiologyOrder::create([
                'clinic_id' => $clinic->id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'ordered_by' => $visit->doctor_id ?? $users->random()->id,
                'study_code' => $studyType?->code ?? 'RAD-'.strtoupper(Str::random(4)),
                'study_name' => $studyType?->name ?? 'General Radiology Study',
                'modality' => Arr::random($modalities),
                'status' => $status,
                'ordered_at' => $visit->started_at ?? now(),
                'notes' => fake()->optional()->sentence(),
            ]);

            if (in_array($status, [RadiologyOrder::STATUS_COMPLETED, RadiologyOrder::STATUS_REPORTED])) {
                RadiologyReport::create([
                    'clinic_id' => $clinic->id,
                    'radiology_order_id' => $radiologyOrder->id,
                    'reported_by' => $users->random()->id,
                    'findings' => 'Normal study. No acute abnormalities detected. '.fake()->sentence(),
                    'impression' => Arr::random([
                        'Normal study',
                        'Mild degenerative changes',
                        'No acute findings',
                        'Follow-up recommended',
                        'Incidental findings noted',
                    ]),
                    'reported_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')),
                ]);

                RadiologyImage::create([
                    'clinic_id' => $clinic->id,
                    'radiology_order_id' => $radiologyOrder->id,
                    'uploaded_by' => $users->random()->id,
                    'dicom_uid' => '1.2.840.'.fake()->numerify('###############'),
                    'file_disk' => 'public',
                    'file_path' => "radiology/{$radiologyOrder->id}/image_001.dcm",
                    'mime_type' => 'application/dicom',
                    'size_bytes' => fake()->numberBetween(1000000, 50000000),
                    'captured_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')),
                    'pacs_study_id' => 'STUDY-'.strtoupper(Str::random(8)),
                    'pacs_instance_id' => 'INST-'.strtoupper(Str::random(8)),
                    'metadata' => ['modality' => $radiologyOrder->modality, 'body_part' => 'Chest'],
                ]);
            }
        }
    }

    private function seedFinancial(Clinic $clinic, EloquentCollection $users, EloquentCollection $invoices): void
    {
        $this->seedExpenseCategories($clinic);
        $this->seedExpenses($clinic, $users);
        $this->seedSalaries($clinic, $users);
        $this->seedCashboxes($clinic, $users);
    }

    private function seedExpenseCategories(Clinic $clinic): void
    {
        $categories = [
            'Rent', 'Utilities', 'Medical Supplies', 'Equipment',
            'Maintenance', 'Marketing', 'Insurance', 'Legal',
            'Training', 'Travel', 'Office Supplies', 'Software Licenses',
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $category],
                [
                    'description' => "{$category} expenses",
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedExpenses(Clinic $clinic, EloquentCollection $users): void
    {
        $categories = ExpenseCategory::where('clinic_id', $clinic->id)->get();

        if ($categories->isEmpty()) {
            return;
        }

        $expenseStatuses = [
            Expense::STATUS_PENDING,
            Expense::STATUS_PENDING,
            Expense::STATUS_APPROVED,
            Expense::STATUS_APPROVED,
            Expense::STATUS_APPROVED,
            Expense::STATUS_REJECTED,
        ];

        for ($i = 0; $i < 30; $i++) {
            $status = Arr::random($expenseStatuses);

            $expense = Expense::create([
                'clinic_id' => $clinic->id,
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
                'description' => fake()->sentence(),
                'amount' => fake()->randomFloat(2, 50, 5000),
                'expense_date' => CarbonImmutable::instance(fake()->dateTimeBetween('-60 days', 'now')),
                'status' => $status,
                'approved_by' => $status !== Expense::STATUS_PENDING ? $users->random()->id : null,
                'approved_at' => $status !== Expense::STATUS_PENDING ? CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')) : null,
                'notes' => fake()->optional()->sentence(),
            ]);
        }
    }

    private function seedSalaries(Clinic $clinic, EloquentCollection $users): void
    {
        $salaryStatuses = [
            Salary::STATUS_DRAFT,
            Salary::STATUS_CALCULATED,
            Salary::STATUS_APPROVED,
            Salary::STATUS_PAID,
        ];

        $sampleUsers = $users->take(min(10, $users->count()));

        foreach ($sampleUsers as $user) {
            for ($month = 1; $month <= 3; $month++) {
                $baseSalary = fake()->randomFloat(2, 3000, 12000);
                $allowances = fake()->randomFloat(2, 0, 2000);
                $deductions = fake()->randomFloat(2, 0, 500);
                $periodMonth = CarbonImmutable::now()->subMonths($month)->format('Y-m');

                $existingSalary = Salary::where('clinic_id', $clinic->id)
                    ->where('user_id', $user->id)
                    ->where('period_month', $periodMonth)
                    ->first();

                if ($existingSalary) {
                    continue;
                }

                $status = $month === 3 ? Arr::random([Salary::STATUS_DRAFT, Salary::STATUS_CALCULATED]) : Arr::random([Salary::STATUS_APPROVED, Salary::STATUS_PAID]);

                Salary::create([
                    'clinic_id' => $clinic->id,
                    'user_id' => $user->id,
                    'base_salary' => $baseSalary,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'net_salary' => $baseSalary + $allowances - $deductions,
                    'status' => $status,
                    'period_month' => $periodMonth,
                    'paid_at' => $status === Salary::STATUS_PAID ? CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')) : null,
                    'paid_by' => $status === Salary::STATUS_PAID ? $users->random()->id : null,
                    'notes' => fake()->optional()->sentence(),
                ]);
            }
        }
    }

    private function seedCashboxes(Clinic $clinic, EloquentCollection $users): void
    {
        for ($day = 0; $day < 10; $day++) {
            $boxDate = CarbonImmutable::now()->subDays($day)->toDateString();

            $existingCashbox = Cashbox::where('clinic_id', $clinic->id)
                ->where('box_date', $boxDate)
                ->first();

            if ($existingCashbox) {
                continue;
            }

            $opening = fake()->randomFloat(2, 500, 5000);
            $income = fake()->randomFloat(2, 1000, 15000);
            $expenses = fake()->randomFloat(2, 200, 3000);
            $isClosed = $day < 8;

            Cashbox::create([
                'clinic_id' => $clinic->id,
                'opening_balance' => $opening,
                'total_income' => $income,
                'total_expenses' => $expenses,
                'closing_balance' => $opening + $income - $expenses,
                'box_date' => $boxDate,
                'status' => $isClosed ? Cashbox::STATUS_CLOSED : Cashbox::STATUS_OPEN,
                'opened_by' => $users->random()->id,
                'opened_at' => CarbonImmutable::now()->subDays($day)->setTime(8, 0),
                'closed_by' => $isClosed ? $users->random()->id : null,
                'closed_at' => $isClosed ? CarbonImmutable::now()->subDays($day)->setTime(18, 0) : null,
                'notes' => fake()->optional()->sentence(),
            ]);
        }
    }

    private function seedAccounting(Clinic $clinic, EloquentCollection $users): void
    {
        $this->seedChartOfAccounts($clinic);
        $this->seedAccountRateTypes($clinic);
        $this->seedJournalEntries($clinic, $users);
    }

    private function seedChartOfAccounts(Clinic $clinic): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash', 'type' => Account::TYPE_ASSET],
            ['code' => '1100', 'name' => 'Bank Account', 'type' => Account::TYPE_ASSET],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => Account::TYPE_ASSET],
            ['code' => '1300', 'name' => 'Inventory', 'type' => Account::TYPE_ASSET],
            ['code' => '1400', 'name' => 'Medical Equipment', 'type' => Account::TYPE_ASSET],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2100', 'name' => 'Accrued Expenses', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2200', 'name' => 'VAT Payable', 'type' => Account::TYPE_LIABILITY],
            ['code' => '3000', 'name' => 'Owner Equity', 'type' => Account::TYPE_EQUITY],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => Account::TYPE_EQUITY],
            ['code' => '4000', 'name' => 'Consultation Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '4100', 'name' => 'Procedure Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '4200', 'name' => 'Pharmacy Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '4300', 'name' => 'Lab Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '5000', 'name' => 'Salaries Expense', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5100', 'name' => 'Rent Expense', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5200', 'name' => 'Supplies Expense', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5300', 'name' => 'Utilities Expense', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5400', 'name' => 'Insurance Expense', 'type' => Account::TYPE_EXPENSE],
        ];

        foreach ($accounts as $acc) {
            Account::updateOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $acc['code']],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'opening_balance' => 0,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedAccountRateTypes(Clinic $clinic): void
    {
        $rateTypes = [
            ['name' => 'VAT', 'code' => 'VAT', 'rate' => 15, 'type' => AccountRateType::TYPE_TAX],
            ['name' => 'Service Tax', 'code' => 'SRV-TAX', 'rate' => 5, 'type' => AccountRateType::TYPE_TAX],
            ['name' => 'Standard Discount', 'code' => 'STD-DISC', 'rate' => 10, 'type' => AccountRateType::TYPE_DISCOUNT],
            ['name' => 'VIP Discount', 'code' => 'VIP-DISC', 'rate' => 20, 'type' => AccountRateType::TYPE_DISCOUNT],
            ['name' => 'Processing Fee', 'code' => 'PROC-FEE', 'rate' => 2, 'type' => AccountRateType::TYPE_OTHER],
        ];

        foreach ($rateTypes as $rate) {
            AccountRateType::updateOrCreate(
                ['clinic_id' => $clinic->id, 'code' => $rate['code']],
                [
                    'name' => $rate['name'],
                    'rate_percentage' => $rate['rate'],
                    'type' => $rate['type'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedJournalEntries(Clinic $clinic, EloquentCollection $users): void
    {
        $accounts = Account::where('clinic_id', $clinic->id)->get();

        if ($accounts->count() < 2) {
            return;
        }

        $statuses = [
            JournalEntry::STATUS_DRAFT,
            JournalEntry::STATUS_POSTED,
            JournalEntry::STATUS_POSTED,
            JournalEntry::STATUS_POSTED,
            JournalEntry::STATUS_VOIDED,
        ];

        for ($i = 0; $i < 15; $i++) {
            $status = Arr::random($statuses);
            $amount = fake()->randomFloat(2, 500, 10000);
            $entryNumber = 'JE-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $existingEntry = JournalEntry::where('clinic_id', $clinic->id)
                ->where('entry_number', $entryNumber)
                ->first();

            if ($existingEntry) {
                continue;
            }

            $debitAccount = $accounts->where('type', Account::TYPE_ASSET)->random();
            $creditAccount = $accounts->where('type', Account::TYPE_REVENUE)->random();

            if (! $creditAccount) {
                $creditAccount = $accounts->random();
            }

            $journalEntry = JournalEntry::create([
                'clinic_id' => $clinic->id,
                'entry_number' => 'JE-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'entry_date' => CarbonImmutable::instance(fake()->dateTimeBetween('-60 days', 'now')),
                'description' => fake()->sentence(),
                'status' => $status,
                'created_by' => $users->random()->id,
                'voided_by' => $status === JournalEntry::STATUS_VOIDED ? $users->random()->id : null,
                'voided_at' => $status === JournalEntry::STATUS_VOIDED ? now() : null,
                'void_reason' => $status === JournalEntry::STATUS_VOIDED ? 'Entry correction needed' : null,
            ]);

            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $debitAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'reference_type' => 'manual',
                'reference_id' => null,
                'notes' => 'Debit entry',
            ]);

            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $creditAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'reference_type' => 'manual',
                'reference_id' => null,
                'notes' => 'Credit entry',
            ]);
        }
    }

    private function seedSuppliersAndPurchasing(Clinic $clinic, EloquentCollection $users): void
    {
        $this->seedSuppliers($clinic);
        $this->seedPurchaseOrders($clinic, $users);
    }

    private function seedSuppliers(Clinic $clinic): void
    {
        $suppliers = [
            ['name' => 'PharmaCo International', 'contact' => 'Ahmed Hassan', 'phone' => '+966501234567', 'email' => 'sales@pharmaco.com'],
            ['name' => 'MedSupply Arabia', 'contact' => 'Sara Al-Rashid', 'phone' => '+966509876543', 'email' => 'orders@medsupply.sa'],
            ['name' => 'HealthDist Group', 'contact' => 'Mohammed Ali', 'phone' => '+966505555555', 'email' => 'info@healthdist.com'],
            ['name' => 'DrugWholesale Ltd', 'contact' => 'Fatima Omar', 'phone' => '+966504444444', 'email' => 'sales@drugwholesale.com'],
            ['name' => 'Gulf Medical Supplies', 'contact' => 'Khalid Ibrahim', 'phone' => '+966503333333', 'email' => 'contact@gulfmed.com'],
        ];

        foreach ($suppliers as $index => $supplier) {
            Supplier::updateOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $supplier['name']],
                [
                    'contact_name' => $supplier['contact'],
                    'phone' => $supplier['phone'],
                    'email' => $supplier['email'],
                    'address' => fake()->address(),
                    'is_active' => $index < 4,
                    'notes' => fake()->optional()->sentence(),
                ],
            );
        }
    }

    private function seedPurchaseOrders(Clinic $clinic, EloquentCollection $users): void
    {
        $suppliers = Supplier::where('clinic_id', $clinic->id)->where('is_active', true)->get();
        $drugs = PharmacyDrug::where('clinic_id', $clinic->id)->where('is_active', true)->get();

        if ($suppliers->isEmpty() || $drugs->isEmpty()) {
            return;
        }

        $statuses = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_ORDERED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_CANCELED,
        ];

        for ($i = 0; $i < 10; $i++) {
            $status = Arr::random($statuses);
            $supplier = $suppliers->random();
            $poNumber = 'PO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $existingPO = PurchaseOrder::where('clinic_id', $clinic->id)
                ->where('po_number', $poNumber)
                ->first();

            if ($existingPO) {
                continue;
            }

            $purchaseOrder = PurchaseOrder::create([
                'clinic_id' => $clinic->id,
                'supplier_id' => $supplier->id,
                'ordered_by' => $users->random()->id,
                'received_by' => in_array($status, [PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED]) ? $users->random()->id : null,
                'po_number' => 'PO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'ordered_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', '-1 day')),
                'expected_at' => CarbonImmutable::instance(fake()->dateTimeBetween('+1 day', '+14 days')),
                'received_at' => $status === PurchaseOrder::STATUS_RECEIVED ? CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')) : null,
                'subtotal_amount' => 0,
                'total_amount' => 0,
                'notes' => fake()->optional()->sentence(),
            ]);

            $itemCount = fake()->numberBetween(2, 5);
            $selectedDrugs = $drugs->shuffle()->take($itemCount);
            $subtotal = 0;

            foreach ($selectedDrugs as $drug) {
                $qty = fake()->numberBetween(50, 500);
                $unitCost = $drug->unit_price * 0.7;
                $lineTotal = $qty * $unitCost;
                $qtyReceived = match ($status) {
                    PurchaseOrder::STATUS_RECEIVED => $qty,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED => (int) ($qty * fake()->randomFloat(2, 0.3, 0.8)),
                    default => 0,
                };

                PurchaseOrderItem::create([
                    'clinic_id' => $clinic->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'pharmacy_drug_id' => $drug->id,
                    'medication_name' => "{$drug->trade_name} {$drug->strength}",
                    'quantity_ordered' => $qty,
                    'quantity_received' => $qtyReceived,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'notes' => fake()->optional()->sentence(),
                ]);

                $subtotal += $lineTotal;
            }

            $purchaseOrder->updateQuietly([
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal,
            ]);
        }
    }

    private function seedWorkflows(Clinic $clinic, EloquentCollection $users): void
    {
        $entityTypes = ['expense', 'purchase_order', 'salary'];

        foreach ($entityTypes as $entityType) {
            $workflow = Workflow::create([
                'clinic_id' => $clinic->id,
                'name' => ucfirst(str_replace('_', ' ', $entityType)).' Approval Workflow',
                'entity_type' => $entityType,
                'trigger_event' => 'created',
                'is_active' => true,
                'created_by' => $users->random()->id,
            ]);

            $steps = [
                ['order' => 1, 'role' => 'clinic_admin', 'action' => 'review', 'auto_hours' => null],
                ['order' => 2, 'role' => 'accountant', 'action' => 'approve', 'auto_hours' => 48],
                ['order' => 3, 'role' => 'super_admin', 'action' => 'final_approve', 'auto_hours' => 24],
            ];

            foreach ($steps as $stepData) {
                WorkflowStep::create([
                    'clinic_id' => $clinic->id,
                    'workflow_id' => $workflow->id,
                    'step_order' => $stepData['order'],
                    'approver_role' => $stepData['role'],
                    'action_required' => $stepData['action'],
                    'auto_approve_after_hours' => $stepData['auto_hours'],
                ]);
            }

            $instanceStatuses = [
                'in_progress',
                'in_progress',
                'approved',
                'rejected',
                'in_progress',
            ];

            for ($i = 0; $i < 5; $i++) {
                $instanceStatus = Arr::random($instanceStatuses);
                $currentStep = $instanceStatus === 'approved' ? 3 : ($instanceStatus === 'rejected' ? fake()->numberBetween(1, 2) : fake()->numberBetween(1, 3));

                $instance = WorkflowInstance::create([
                    'clinic_id' => $clinic->id,
                    'workflow_id' => $workflow->id,
                    'entity_type' => $entityType,
                    'entity_id' => fake()->numberBetween(1, 100),
                    'status' => $instanceStatus,
                    'current_step' => $currentStep,
                    'completed_at' => in_array($instanceStatus, ['approved', 'rejected']) ? CarbonImmutable::instance(fake()->dateTimeBetween('-10 days', 'now')) : null,
                ]);

                foreach ($workflow->steps as $step) {
                    $approvalStatus = match (true) {
                        $instanceStatus === 'approved' => 'approved',
                        $instanceStatus === 'rejected' && $step->step_order === $currentStep => 'rejected',
                        $instanceStatus === 'rejected' && $step->step_order < $currentStep => 'approved',
                        $step->step_order < $currentStep => 'approved',
                        $step->step_order === $currentStep && $instanceStatus === 'in_progress' => 'pending',
                        default => 'pending',
                    };

                    WorkflowApproval::create([
                        'clinic_id' => $clinic->id,
                        'instance_id' => $instance->id,
                        'step_id' => $step->id,
                        'approver_id' => in_array($approvalStatus, ['approved', 'rejected']) ? $users->random()->id : null,
                        'status' => $approvalStatus,
                        'comments' => in_array($approvalStatus, ['approved', 'rejected']) ? fake()->sentence() : null,
                        'decided_at' => in_array($approvalStatus, ['approved', 'rejected']) ? CarbonImmutable::instance(fake()->dateTimeBetween('-10 days', 'now')) : null,
                    ]);
                }
            }
        }
    }

    private function seedPaymentPlans(Clinic $clinic, EloquentCollection $invoices, EloquentCollection $users): void
    {
        $planNames = [
            '3-Month Payment Plan',
            '6-Month Payment Plan',
            '12-Month Payment Plan',
            'Quarterly Payment Plan',
            'Weekly Payment Plan',
        ];

        $frequencies = ['monthly', 'quarterly', 'weekly'];

        foreach ($planNames as $index => $planName) {
            $installmentCount = [3, 6, 12, 4, 12][$index];
            $frequency = $frequencies[min($index, count($frequencies) - 1)];

            $paymentPlan = PaymentPlan::create([
                'clinic_id' => $clinic->id,
                'name' => $planName,
                'description' => "Payment plan with {$installmentCount} {$frequency} installments",
                'installment_count' => $installmentCount,
                'frequency' => $frequency,
                'min_amount' => Arr::random([5000, 10000, 25000, 50000]),
                'is_active' => true,
                'created_by' => $users->random()->id,
            ]);

            $linkedInvoices = $invoices->shuffle()->take(fake()->numberBetween(1, 3));

            foreach ($linkedInvoices as $invoice) {
                $totalAmount = (float) $invoice->total_amount;

                if ($totalAmount <= 0) {
                    continue;
                }

                $installmentAmount = round($totalAmount / $installmentCount, 2);

                for ($i = 1; $i <= $installmentCount; $i++) {
                    $status = match (true) {
                        $i === 1 => Arr::random(['paid', 'pending']),
                        $i <= 2 => Arr::random(['paid', 'pending', 'overdue']),
                        default => Arr::random(['pending', 'overdue']),
                    };

                    Installment::create([
                        'clinic_id' => $clinic->id,
                        'payment_plan_id' => $paymentPlan->id,
                        'invoice_id' => $invoice->id,
                        'installment_number' => $i,
                        'amount' => $installmentAmount,
                        'due_date' => CarbonImmutable::now()->addMonths($i - 1)->toDateString(),
                        'status' => $status === 'overdue' ? 'pending' : $status,
                        'paid_amount' => $status === 'paid' ? $installmentAmount : 0,
                        'paid_at' => $status === 'paid' ? CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')) : null,
                        'notes' => fake()->optional()->sentence(),
                    ]);
                }
            }
        }
    }

    private function seedSecurityAndCompliance(Clinic $clinic, EloquentCollection $users, EloquentCollection $patients): void
    {
        $this->seedSecurityPolicy($clinic, $users);
        $this->seedBrandingSetting($clinic);
        $this->seedAuthAttemptLogs($clinic, $users);
        $this->seedSensitiveAccessLogs($clinic, $users, $patients);
        $this->seedComplianceRuns($clinic, $users);
        $this->seedUserInvitations($clinic, $users);
    }

    private function seedSecurityPolicy(Clinic $clinic, EloquentCollection $users): void
    {
        SecurityPolicy::updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'updated_by' => $users->first()?->id,
                'password_min_length' => 8,
                'require_mixed_case' => true,
                'require_numbers' => true,
                'require_symbols' => true,
                'force_two_factor' => false,
                'confirm_password_for_security_actions' => true,
                'audit_retention_days' => 90,
                'sensitive_access_retention_days' => 30,
                'session_lifetime_minutes' => 480,
                'idle_timeout_minutes' => 30,
            ],
        );
    }

    private function seedBrandingSetting(Clinic $clinic): void
    {
        BrandingSetting::updateOrCreate(
            ['clinic_id' => $clinic->id],
            [
                'company_name' => $clinic->name,
                'logo_path' => null,
                'theme_tokens' => [
                    'primary_color' => '#0D6EFD',
                    'secondary_color' => '#6C757D',
                    'font_family' => 'Inter',
                ],
                'locale_default' => 'en',
                'domain' => null,
            ],
        );
    }

    private function seedAuthAttemptLogs(Clinic $clinic, EloquentCollection $users): void
    {
        $statuses = [
            AuthAttemptLog::STATUS_SUCCESS,
            AuthAttemptLog::STATUS_SUCCESS,
            AuthAttemptLog::STATUS_SUCCESS,
            AuthAttemptLog::STATUS_FAILED,
            AuthAttemptLog::STATUS_FAILED,
            AuthAttemptLog::STATUS_LOCKOUT,
        ];

        $failureReasons = [
            'Invalid password',
            'Account not found',
            'Account locked',
            'Two-factor authentication failed',
            'Expired session',
        ];

        for ($i = 0; $i < 40; $i++) {
            $status = Arr::random($statuses);
            $user = $users->random();

            AuthAttemptLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $status !== AuthAttemptLog::STATUS_FAILED ? $user->id : null,
                'email' => $user->email,
                'status' => $status,
                'failure_reason' => $status !== AuthAttemptLog::STATUS_SUCCESS ? Arr::random($failureReasons) : null,
                'ip_address' => fake()->ipv4(),
                'user_agent' => Arr::random([
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) Safari/17.2',
                    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile/15E148',
                    'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile',
                ]),
                'metadata' => ['browser' => 'Chrome', 'os' => 'Windows'],
                'occurred_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')),
            ]);
        }
    }

    private function seedSensitiveAccessLogs(Clinic $clinic, EloquentCollection $users, EloquentCollection $patients): void
    {
        $resourceTypes = ['patient_record', 'medical_history', 'financial_record', 'prescription'];

        $reasons = [
            'Treatment purposes',
            'Billing inquiry',
            'Insurance claim',
            'Legal request',
            'Patient request',
            'Audit review',
        ];

        $samplePatients = $patients->take(min(20, $patients->count()));

        for ($i = 0; $i < 30; $i++) {
            SensitiveAccessLog::create([
                'clinic_id' => $clinic->id,
                'user_id' => $users->random()->id,
                'patient_id' => $samplePatients->random()->id,
                'resource_type' => Arr::random($resourceTypes),
                'resource_id' => fake()->numberBetween(1, 100),
                'reason' => Arr::random($reasons),
                'context' => ['accessed_from' => 'dashboard', 'module' => 'patients'],
                'accessed_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')),
            ]);
        }
    }

    private function seedComplianceRuns(Clinic $clinic, EloquentCollection $users): void
    {
        $runTypes = ['audit_log_review', 'access_pattern_analysis', 'data_retention_check', 'security_scan'];

        for ($i = 0; $i < 5; $i++) {
            ComplianceRun::create([
                'clinic_id' => $clinic->id,
                'ran_by' => $users->random()->id,
                'run_type' => Arr::random($runTypes),
                'status' => Arr::random(['completed', 'completed', 'completed', 'failed', 'in_progress']),
                'summary' => [
                    'total_records_reviewed' => fake()->numberBetween(100, 1000),
                    'flags_raised' => fake()->numberBetween(0, 10),
                    'issues_resolved' => fake()->numberBetween(0, 5),
                ],
                'ran_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-60 days', 'now')),
            ]);
        }
    }

    private function seedUserInvitations(Clinic $clinic, EloquentCollection $users): void
    {
        $roles = Role::where('clinic_id', $clinic->id)->get();

        $invitations = [
            ['email' => 'new.doctor@example.com', 'name' => 'Dr. New Doctor', 'role' => 'doctor'],
            ['email' => 'new.nurse@example.com', 'name' => 'Nurse New', 'role' => 'nurse'],
            ['email' => 'new.receptionist@example.com', 'name' => 'Receptionist New', 'role' => 'receptionist'],
        ];

        foreach ($invitations as $inv) {
            $isAccepted = fake()->boolean(40);
            $role = $roles->where('name', $inv['role'])->first() ?? $roles->first();

            UserInvitation::create([
                'clinic_id' => $clinic->id,
                'invited_by' => $users->random()->id,
                'accepted_user_id' => $isAccepted ? $users->random()->id : null,
                'email' => $inv['email'],
                'full_name' => $inv['name'],
                'role_name' => $role?->name ?? 'receptionist',
                'token' => Str::random(40),
                'expires_at' => CarbonImmutable::now()->addDays(7),
                'accepted_at' => $isAccepted ? CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')) : null,
                'metadata' => ['invited_via' => 'admin_panel'],
            ]);
        }

        UserInvitation::create([
            'clinic_id' => $clinic->id,
            'invited_by' => $users->random()->id,
            'accepted_user_id' => null,
            'email' => 'expired.invite@example.com',
            'full_name' => 'Expired Invite',
            'role_name' => 'receptionist',
            'token' => Str::random(40),
            'expires_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', '-1 day')),
            'accepted_at' => null,
            'metadata' => ['invited_via' => 'admin_panel'],
        ]);
    }

    private function seedPatientPortal(Clinic $clinic, EloquentCollection $patients, EloquentCollection $users): void
    {
        $samplePatients = $patients->take(min(10, $patients->count()));

        foreach ($samplePatients as $patient) {
            $isActive = fake()->boolean(60);

            PatientPortalToken::create([
                'clinic_id' => $clinic->id,
                'patient_id' => $patient->id,
                'created_by' => $users->random()->id,
                'token_hash' => hash('sha256', Str::random(40)),
                'expires_at' => $isActive ? CarbonImmutable::now()->addDays(30) : CarbonImmutable::instance(fake()->dateTimeBetween('-10 days', 'now')),
                'last_used_at' => $isActive ? CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')) : null,
                'revoked_at' => ! $isActive ? CarbonImmutable::instance(fake()->dateTimeBetween('-5 days', 'now')) : null,
                'notes' => fake()->optional()->sentence(),
            ]);
        }
    }

    private function seedExternalIntegrations(Clinic $clinic, EloquentCollection $users): void
    {
        $integrationTypes = [ExternalIntegration::TYPE_LIS_HL7, ExternalIntegration::TYPE_PACS];
        $referenceTypes = ['lab_order', 'radiology_order'];
        $statuses = [
            ExternalIntegration::STATUS_QUEUED,
            ExternalIntegration::STATUS_SENT,
            ExternalIntegration::STATUS_SENT,
            ExternalIntegration::STATUS_FAILED,
        ];

        for ($i = 0; $i < 10; $i++) {
            ExternalIntegration::create([
                'clinic_id' => $clinic->id,
                'created_by' => $users->random()->id,
                'integration_type' => Arr::random($integrationTypes),
                'reference_type' => Arr::random($referenceTypes),
                'reference_id' => fake()->numberBetween(1, 100),
                'status' => Arr::random($statuses),
                'request_payload' => ['test_code' => 'CBC', 'patient_id' => fake()->numberBetween(1, 100)],
                'response_payload' => fake()->boolean(60) ? ['status' => 'success', 'result_id' => fake()->numberBetween(1000, 9999)] : null,
                'error_message' => fake()->boolean(20) ? 'Connection timeout after 30s' : null,
                'sent_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')),
            ]);
        }
    }
}
