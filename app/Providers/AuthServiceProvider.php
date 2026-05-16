<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Cashbox;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Expense;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PharmacyDrug;
use App\Models\Prescription;
use App\Models\QueueEntry;
use App\Models\RadiologyOrder;
use App\Models\Role;
use App\Models\Salary;
use App\Models\User;
use App\Models\Visit;
use App\Policies\AppointmentPolicy;
use App\Policies\CashboxPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DoctorProfilePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InstallmentPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LabOrderPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PaymentPlanPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PharmacyDrugPolicy;
use App\Policies\PrescriptionPolicy;
use App\Policies\QueueEntryPolicy;
use App\Policies\RadiologyOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalaryPolicy;
use App\Policies\UserPolicy;
use App\Policies\VisitPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Appointment::class => AppointmentPolicy::class,
        Cashbox::class => CashboxPolicy::class,
        Department::class => DepartmentPolicy::class,
        DoctorProfile::class => DoctorProfilePolicy::class,
        Expense::class => ExpensePolicy::class,
        Installment::class => InstallmentPolicy::class,
        Invoice::class => InvoicePolicy::class,
        LabOrder::class => LabOrderPolicy::class,
        Patient::class => PatientPolicy::class,
        Payment::class => PaymentPolicy::class,
        PaymentPlan::class => PaymentPlanPolicy::class,
        PharmacyDrug::class => PharmacyDrugPolicy::class,
        Prescription::class => PrescriptionPolicy::class,
        QueueEntry::class => QueueEntryPolicy::class,
        RadiologyOrder::class => RadiologyOrderPolicy::class,
        Role::class => RolePolicy::class,
        Salary::class => SalaryPolicy::class,
        User::class => UserPolicy::class,
        Visit::class => VisitPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
