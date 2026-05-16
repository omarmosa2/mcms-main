<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class DatabaseSeeder extends Seeder
{
    private const CLINIC_COUNT = 2;

    private const STAFF_PER_CLINIC = 12;

    private const PATIENTS_PER_CLINIC = 80;

    private const APPOINTMENTS_PER_CLINIC = 120;

    private const QUEUE_ENTRIES_PER_CLINIC = 100;

    private const VISITS_PER_CLINIC = 70;

    private const EXTRA_INVOICES_PER_CLINIC = 30;

    private const AUDIT_LOGS_PER_CLINIC = 120;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // MVP lightweight seed
        $this->call([
            MvpSeeder::class,
            NumberRangeSeeder::class,
        ]);

        // Full system seed with all modules and scenarios
        $this->call([
            FullSystemSeeder::class,
        ]);
    }

    private function seedClinicData(Clinic $clinic): void
    {
        $staffMembers = User::factory($this->countFor(self::STAFF_PER_CLINIC))->create([
            'clinic_id' => $clinic->id,
        ]);

        $this->assignRolesToStaff($clinic, $staffMembers);

        $patients = Patient::factory($this->countFor(self::PATIENTS_PER_CLINIC))->create([
            'clinic_id' => $clinic->id,
        ]);

        $appointments = $this->createAppointments($clinic, $patients, $staffMembers);
        $queueEntries = $this->createQueueEntries($clinic, $patients, $appointments, $staffMembers);
        $visits = $this->createVisits($clinic, $queueEntries, $appointments, $staffMembers);
        $invoices = $this->createInvoices($clinic, $visits, $appointments, $staffMembers);
        $payments = $this->createPayments($clinic, $invoices, $staffMembers);

        $this->refreshInvoiceTotalsAndStatuses($invoices);
        $this->createAuditLogs($clinic, $staffMembers, $patients, $appointments, $queueEntries, $visits, $invoices, $payments);
    }

    private function seedDemoAdmin(?Clinic $clinic): void
    {
        if ($clinic === null) {
            return;
        }

        $demoUser = User::query()->updateOrCreate(
            [
                'email' => 'demo.admin@example.com',
            ],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Demo Admin',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $superAdminRole = Role::query()
            ->where('clinic_id', $clinic->id)
            ->where('name', 'super_admin')
            ->first();

        if ($superAdminRole !== null) {
            $demoUser->assignRole($superAdminRole, $demoUser->id);
        }
    }

    /**
     * @param  EloquentCollection<int, User>  $staffMembers
     */
    private function assignRolesToStaff(Clinic $clinic, EloquentCollection $staffMembers): void
    {
        if ($staffMembers->isEmpty()) {
            return;
        }

        $roles = Role::query()
            ->where('clinic_id', $clinic->id)
            ->get()
            ->keyBy('name');

        if ($roles->isEmpty()) {
            return;
        }

        $firstStaffMember = $staffMembers->first();
        $superAdminRole = $roles->get('super_admin', $roles->first());

        if ($firstStaffMember !== null && $superAdminRole !== null) {
            $firstStaffMember->assignRole($superAdminRole, $firstStaffMember->id);
        }

        $assignableRoles = $roles->except('super_admin')->values();

        foreach ($staffMembers->slice(1) as $staffMember) {
            $role = $assignableRoles->isNotEmpty()
                ? $assignableRoles->random()
                : $superAdminRole;

            if ($role !== null) {
                $staffMember->assignRole($role, $firstStaffMember?->id);
            }
        }
    }

    /**
     * @param  EloquentCollection<int, Patient>  $patients
     * @param  EloquentCollection<int, User>  $staffMembers
     * @return EloquentCollection<int, Appointment>
     */
    private function createAppointments(Clinic $clinic, EloquentCollection $patients, EloquentCollection $staffMembers): EloquentCollection
    {
        $appointments = new EloquentCollection;
        $patientIds = $patients->pluck('id')->all();
        $staffIds = $staffMembers->pluck('id')->all();
        $count = $this->countFor(self::APPOINTMENTS_PER_CLINIC);

        for ($index = 0; $index < $count; $index++) {
            $status = Arr::random([
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_ARRIVED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELED,
                Appointment::STATUS_NO_SHOW,
            ]);

            $scheduledFor = $this->appointmentScheduleFor($status);
            $statusDates = $this->appointmentStatusDates($status, $scheduledFor);

            $appointments->push(
                Appointment::factory()->create([
                    'clinic_id' => $clinic->id,
                    'patient_id' => Arr::random($patientIds),
                    'doctor_id' => Arr::random($staffIds),
                    'created_by' => Arr::random($staffIds),
                    'scheduled_for' => $scheduledFor,
                    'duration_minutes' => fake()->randomElement([15, 20, 30, 45, 60]),
                    'status' => $status,
                    'arrived_at' => $statusDates['arrived_at'],
                    'completed_at' => $statusDates['completed_at'],
                    'canceled_at' => $statusDates['canceled_at'],
                    'cancel_reason' => $statusDates['cancel_reason'],
                ]),
            );
        }

        return $appointments;
    }

    /**
     * @param  EloquentCollection<int, Patient>  $patients
     * @param  EloquentCollection<int, Appointment>  $appointments
     * @param  EloquentCollection<int, User>  $staffMembers
     * @return EloquentCollection<int, QueueEntry>
     */
    private function createQueueEntries(
        Clinic $clinic,
        EloquentCollection $patients,
        EloquentCollection $appointments,
        EloquentCollection $staffMembers,
    ): EloquentCollection {
        $queueEntries = new EloquentCollection;
        $patientIds = $patients->pluck('id')->all();
        $staffIds = $staffMembers->pluck('id')->all();
        $count = $this->countFor(self::QUEUE_ENTRIES_PER_CLINIC);
        $queueNumbersByDate = [];

        for ($index = 0; $index < $count; $index++) {
            $status = Arr::random([
                QueueEntry::STATUS_WAITING,
                QueueEntry::STATUS_WAITING,
                QueueEntry::STATUS_CALLED,
                QueueEntry::STATUS_IN_SERVICE,
                QueueEntry::STATUS_COMPLETED,
                QueueEntry::STATUS_COMPLETED,
                QueueEntry::STATUS_SKIPPED,
                QueueEntry::STATUS_CANCELED,
            ]);

            $linkedAppointment = null;

            if ($appointments->isNotEmpty() && fake()->boolean(70)) {
                $linkedAppointment = $appointments->random();
            }

            $queueDate = $linkedAppointment !== null
                ? CarbonImmutable::parse((string) $linkedAppointment->scheduled_for)->toDateString()
                : CarbonImmutable::instance(fake()->dateTimeBetween('-20 days', 'now'))->toDateString();

            $queueNumber = ($queueNumbersByDate[$queueDate] ?? 0) + 1;
            $queueNumbersByDate[$queueDate] = $queueNumber;

            $checkedInAt = CarbonImmutable::parse($queueDate)
                ->setTime(
                    fake()->numberBetween(8, 18),
                    fake()->randomElement([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]),
                );

            $statusDates = $this->queueStatusDates($status, $checkedInAt);

            $queueEntries->push(
                QueueEntry::factory()->create([
                    'clinic_id' => $clinic->id,
                    'appointment_id' => $linkedAppointment?->id,
                    'patient_id' => $linkedAppointment?->patient_id ?? Arr::random($patientIds),
                    'assigned_doctor_id' => Arr::random($staffIds),
                    'called_by' => fake()->boolean(70) ? Arr::random($staffIds) : null,
                    'queue_date' => $queueDate,
                    'queue_number' => $queueNumber,
                    'priority' => fake()->numberBetween(0, 3),
                    'status' => $status,
                    'checked_in_at' => $checkedInAt,
                    'called_at' => $statusDates['called_at'],
                    'started_at' => $statusDates['started_at'],
                    'completed_at' => $statusDates['completed_at'],
                ]),
            );
        }

        return $queueEntries;
    }

    /**
     * @param  EloquentCollection<int, QueueEntry>  $queueEntries
     * @param  EloquentCollection<int, Appointment>  $appointments
     * @param  EloquentCollection<int, User>  $staffMembers
     * @return EloquentCollection<int, Visit>
     */
    private function createVisits(
        Clinic $clinic,
        EloquentCollection $queueEntries,
        EloquentCollection $appointments,
        EloquentCollection $staffMembers,
    ): EloquentCollection {
        $eligibleQueueEntries = $queueEntries
            ->whereIn('status', [QueueEntry::STATUS_IN_SERVICE, QueueEntry::STATUS_COMPLETED])
            ->shuffle()
            ->values();

        $visits = new EloquentCollection;
        $staffIds = $staffMembers->pluck('id')->all();
        $count = min($this->countFor(self::VISITS_PER_CLINIC), $eligibleQueueEntries->count());

        for ($index = 0; $index < $count; $index++) {
            $queueEntry = $eligibleQueueEntries[$index];

            $status = $queueEntry->status === QueueEntry::STATUS_COMPLETED && fake()->boolean(80)
                ? Visit::STATUS_COMPLETED
                : Arr::random([
                    Visit::STATUS_STARTED,
                    Visit::STATUS_IN_PROGRESS,
                ]);

            $startedAt = $queueEntry->started_at !== null
                ? CarbonImmutable::parse((string) $queueEntry->started_at)
                : CarbonImmutable::parse((string) $queueEntry->checked_in_at)->addMinutes(fake()->numberBetween(5, 20));

            $statusDates = $this->visitStatusDates($status, $startedAt);

            $appointmentId = $queueEntry->appointment_id;

            if ($appointmentId === null) {
                $fallbackAppointment = $appointments->firstWhere('patient_id', $queueEntry->patient_id);
                $appointmentId = $fallbackAppointment?->id;
            }

            $visits->push(
                Visit::factory()->create([
                    'clinic_id' => $clinic->id,
                    'queue_entry_id' => $queueEntry->id,
                    'appointment_id' => $appointmentId,
                    'patient_id' => $queueEntry->patient_id,
                    'doctor_id' => $queueEntry->assigned_doctor_id ?? Arr::random($staffIds),
                    'status' => $status,
                    'started_at' => $startedAt,
                    'in_progress_at' => $statusDates['in_progress_at'],
                    'completed_at' => $statusDates['completed_at'],
                ]),
            );
        }

        return $visits;
    }

    /**
     * @param  EloquentCollection<int, Visit>  $visits
     * @param  EloquentCollection<int, Appointment>  $appointments
     * @param  EloquentCollection<int, User>  $staffMembers
     * @return EloquentCollection<int, Invoice>
     */
    private function createInvoices(
        Clinic $clinic,
        EloquentCollection $visits,
        EloquentCollection $appointments,
        EloquentCollection $staffMembers,
    ): EloquentCollection {
        $invoices = new EloquentCollection;
        $staffIds = $staffMembers->pluck('id')->all();

        $visitInvoices = $visits
            ->shuffle()
            ->take(min($visits->count(), $this->countFor(self::VISITS_PER_CLINIC)))
            ->values();

        foreach ($visitInvoices as $visit) {
            $invoices->push(
                $this->createInvoiceWithItems(
                    clinic: $clinic,
                    patientId: (int) $visit->patient_id,
                    issuedBy: Arr::random($staffIds),
                    visitId: $visit->id,
                    appointmentId: $visit->appointment_id,
                ),
            );
        }

        $visitedAppointmentIds = $visits->pluck('appointment_id')->filter()->all();

        $additionalAppointments = $appointments
            ->whereIn('status', [
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_ARRIVED,
                Appointment::STATUS_COMPLETED,
            ])
            ->whereNotIn('id', $visitedAppointmentIds)
            ->shuffle()
            ->take($this->countFor(self::EXTRA_INVOICES_PER_CLINIC));

        foreach ($additionalAppointments as $appointment) {
            $invoices->push(
                $this->createInvoiceWithItems(
                    clinic: $clinic,
                    patientId: (int) $appointment->patient_id,
                    issuedBy: Arr::random($staffIds),
                    visitId: null,
                    appointmentId: $appointment->id,
                ),
            );
        }

        return $invoices;
    }

    /**
     * @param  EloquentCollection<int, Invoice>  $invoices
     * @param  EloquentCollection<int, User>  $staffMembers
     * @return EloquentCollection<int, Payment>
     */
    private function createPayments(Clinic $clinic, EloquentCollection $invoices, EloquentCollection $staffMembers): EloquentCollection
    {
        $payments = new EloquentCollection;
        $staffIds = $staffMembers->pluck('id')->all();

        foreach ($invoices as $invoice) {
            $totalAmount = (float) $invoice->total_amount;

            if ($totalAmount <= 0) {
                continue;
            }

            $paymentMode = Arr::random([
                'none',
                'partial',
                'partial',
                'full',
                'full',
                'full',
            ]);

            if ($paymentMode === 'none') {
                continue;
            }

            $primaryAmount = $paymentMode === 'full'
                ? $totalAmount
                : round($totalAmount * fake()->randomFloat(2, 0.35, 0.75), 2);

            $primaryAmount = max(1, min($primaryAmount, $totalAmount));

            $payments->push(
                Payment::factory()->create([
                    'clinic_id' => $clinic->id,
                    'invoice_id' => $invoice->id,
                    'received_by' => Arr::random($staffIds),
                    'amount' => $primaryAmount,
                    'paid_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-20 days', 'now')),
                    'status' => Payment::STATUS_RECORDED,
                ]),
            );

            $remainingAmount = round($totalAmount - $primaryAmount, 2);

            if ($paymentMode === 'partial' && $remainingAmount > 10 && fake()->boolean(35)) {
                $secondaryAmount = round(min($remainingAmount, $remainingAmount * fake()->randomFloat(2, 0.4, 0.9)), 2);

                if ($secondaryAmount > 0) {
                    $payments->push(
                        Payment::factory()->create([
                            'clinic_id' => $clinic->id,
                            'invoice_id' => $invoice->id,
                            'received_by' => Arr::random($staffIds),
                            'amount' => $secondaryAmount,
                            'paid_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-15 days', 'now')),
                            'status' => Payment::STATUS_RECORDED,
                        ]),
                    );
                }
            }
        }

        return $payments;
    }

    /**
     * @param  EloquentCollection<int, Invoice>  $invoices
     */
    private function refreshInvoiceTotalsAndStatuses(EloquentCollection $invoices): void
    {
        foreach ($invoices as $invoice) {
            $invoice->refresh();

            $total = round((float) $invoice->total_amount, 2);
            $paid = round((float) $invoice->payments()->where('status', Payment::STATUS_RECORDED)->sum('amount'), 2);
            $balance = round(max($total - $paid, 0), 2);

            $status = Invoice::STATUS_ISSUED;

            if ($paid > 0 && $balance > 0) {
                $status = Invoice::STATUS_PARTIALLY_PAID;
            }

            if ($balance <= 0 && $paid > 0) {
                $status = Invoice::STATUS_PAID;
            }

            $invoice->update([
                'status' => $status,
                'paid_amount' => $paid,
                'balance_amount' => $balance,
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, User>  $staffMembers
     * @param  EloquentCollection<int, Patient>  $patients
     * @param  EloquentCollection<int, Appointment>  $appointments
     * @param  EloquentCollection<int, QueueEntry>  $queueEntries
     * @param  EloquentCollection<int, Visit>  $visits
     * @param  EloquentCollection<int, Invoice>  $invoices
     * @param  EloquentCollection<int, Payment>  $payments
     */
    private function createAuditLogs(
        Clinic $clinic,
        EloquentCollection $staffMembers,
        EloquentCollection $patients,
        EloquentCollection $appointments,
        EloquentCollection $queueEntries,
        EloquentCollection $visits,
        EloquentCollection $invoices,
        EloquentCollection $payments,
    ): void {
        $staffIds = $staffMembers->pluck('id')->all();

        $auditableRecords = collect()
            ->concat($patients->all())
            ->concat($appointments->all())
            ->concat($queueEntries->all())
            ->concat($visits->all())
            ->concat($invoices->all())
            ->concat($payments->all())
            ->values();

        if ($auditableRecords->isEmpty()) {
            return;
        }

        $count = $this->countFor(self::AUDIT_LOGS_PER_CLINIC);

        for ($index = 0; $index < $count; $index++) {
            $auditable = $auditableRecords->random();
            $action = Arr::random([
                'created',
                'updated',
                'status_changed',
                'reviewed',
            ]);

            $newValues = array_filter([
                'status' => $auditable->getAttribute('status'),
                'model' => class_basename($auditable),
            ], static fn (mixed $value): bool => $value !== null);

            $oldValues = in_array($action, ['updated', 'status_changed'], true)
                ? [
                    'status' => 'previous_state',
                ]
                : null;

            AuditLog::factory()->create([
                'clinic_id' => $clinic->id,
                'user_id' => fake()->boolean(90) ? Arr::random($staffIds) : null,
                'action' => $action,
                'auditable_type' => $auditable::class,
                'auditable_id' => $auditable->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => [
                    'seed' => 'database',
                    'module' => class_basename($auditable),
                ],
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'occurred_at' => CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', 'now')),
            ]);
        }
    }

    private function createInvoiceWithItems(
        Clinic $clinic,
        int $patientId,
        int $issuedBy,
        ?int $visitId,
        ?int $appointmentId,
    ): Invoice {
        $issuedAt = CarbonImmutable::instance(fake()->dateTimeBetween('-25 days', 'now'));

        $invoice = Invoice::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'appointment_id' => $appointmentId,
            'issued_by' => $issuedBy,
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->addDays(fake()->numberBetween(7, 30))->toDateString(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance_amount' => 0,
        ]);

        $itemCount = fake()->numberBetween(1, 4);

        for ($index = 0; $index < $itemCount; $index++) {
            InvoiceItem::factory()->create([
                'clinic_id' => $clinic->id,
                'invoice_id' => $invoice->id,
            ]);
        }

        $items = $invoice->items()->get();

        $subtotal = round($items->sum(
            static fn (InvoiceItem $item): float => ((float) $item->quantity) * ((float) $item->unit_price),
        ), 2);

        $discount = round((float) $items->sum('discount_amount'), 2);
        $tax = round((float) $items->sum('tax_amount'), 2);
        $total = round(max(0, $subtotal - $discount + $tax), 2);

        $invoice->update([
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'paid_amount' => 0,
            'balance_amount' => $total,
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $invoice->refresh();

        return $invoice;
    }

    private function appointmentScheduleFor(string $status): CarbonImmutable
    {
        return match ($status) {
            Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED => CarbonImmutable::instance(fake()->dateTimeBetween('-2 days', '+20 days')),
            Appointment::STATUS_ARRIVED => CarbonImmutable::instance(fake()->dateTimeBetween('-2 days', 'now')),
            default => CarbonImmutable::instance(fake()->dateTimeBetween('-25 days', '-1 day')),
        };
    }

    /**
     * @return array{
     *     arrived_at: ?CarbonImmutable,
     *     completed_at: ?CarbonImmutable,
     *     canceled_at: ?CarbonImmutable,
     *     cancel_reason: ?string
     * }
     */
    private function appointmentStatusDates(string $status, CarbonImmutable $scheduledFor): array
    {
        return match ($status) {
            Appointment::STATUS_ARRIVED => [
                'arrived_at' => $scheduledFor->addMinutes(fake()->numberBetween(-10, 30)),
                'completed_at' => null,
                'canceled_at' => null,
                'cancel_reason' => null,
            ],
            Appointment::STATUS_COMPLETED => [
                'arrived_at' => $scheduledFor->addMinutes(fake()->numberBetween(-5, 20)),
                'completed_at' => $scheduledFor->addMinutes(fake()->numberBetween(25, 90)),
                'canceled_at' => null,
                'cancel_reason' => null,
            ],
            Appointment::STATUS_CANCELED => [
                'arrived_at' => null,
                'completed_at' => null,
                'canceled_at' => $scheduledFor->subHours(fake()->numberBetween(1, 48)),
                'cancel_reason' => fake()->randomElement([
                    'Patient requested reschedule',
                    'Doctor unavailable',
                    'Unexpected clinic closure',
                ]),
            ],
            default => [
                'arrived_at' => null,
                'completed_at' => null,
                'canceled_at' => null,
                'cancel_reason' => null,
            ],
        };
    }

    /**
     * @return array{
     *     called_at: ?CarbonImmutable,
     *     started_at: ?CarbonImmutable,
     *     completed_at: ?CarbonImmutable
     * }
     */
    private function queueStatusDates(string $status, CarbonImmutable $checkedInAt): array
    {
        return match ($status) {
            QueueEntry::STATUS_CALLED => [
                'called_at' => $checkedInAt->addMinutes(fake()->numberBetween(3, 20)),
                'started_at' => null,
                'completed_at' => null,
            ],
            QueueEntry::STATUS_IN_SERVICE => [
                'called_at' => $checkedInAt->addMinutes(fake()->numberBetween(2, 15)),
                'started_at' => $checkedInAt->addMinutes(fake()->numberBetween(10, 30)),
                'completed_at' => null,
            ],
            QueueEntry::STATUS_COMPLETED => [
                'called_at' => $checkedInAt->addMinutes(fake()->numberBetween(2, 15)),
                'started_at' => $checkedInAt->addMinutes(fake()->numberBetween(10, 30)),
                'completed_at' => $checkedInAt->addMinutes(fake()->numberBetween(30, 90)),
            ],
            QueueEntry::STATUS_SKIPPED => [
                'called_at' => $checkedInAt->addMinutes(fake()->numberBetween(5, 20)),
                'started_at' => null,
                'completed_at' => $checkedInAt->addMinutes(fake()->numberBetween(25, 50)),
            ],
            QueueEntry::STATUS_CANCELED => [
                'called_at' => null,
                'started_at' => null,
                'completed_at' => $checkedInAt->addMinutes(fake()->numberBetween(10, 40)),
            ],
            default => [
                'called_at' => null,
                'started_at' => null,
                'completed_at' => null,
            ],
        };
    }

    /**
     * @return array{
     *     in_progress_at: ?CarbonImmutable,
     *     completed_at: ?CarbonImmutable
     * }
     */
    private function visitStatusDates(string $status, CarbonImmutable $startedAt): array
    {
        return match ($status) {
            Visit::STATUS_IN_PROGRESS => [
                'in_progress_at' => $startedAt->addMinutes(fake()->numberBetween(5, 20)),
                'completed_at' => null,
            ],
            Visit::STATUS_COMPLETED => [
                'in_progress_at' => $startedAt->addMinutes(fake()->numberBetween(5, 20)),
                'completed_at' => $startedAt->addMinutes(fake()->numberBetween(35, 110)),
            ],
            default => [
                'in_progress_at' => null,
                'completed_at' => null,
            ],
        };
    }

    private function countFor(int $fullCount): int
    {
        if (app()->runningUnitTests()) {
            return max(2, (int) ceil($fullCount / 5));
        }

        return $fullCount;
    }
}
