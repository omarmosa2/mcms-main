<?php

namespace App\Models;

use Database\Factories\ClinicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Clinic extends Model
{
    /** @use HasFactory<ClinicFactory> */
    use HasFactory;

    protected $guarded = [];

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function patientCardVisits(): HasMany
    {
        return $this->hasMany(PatientCardVisit::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(ClinicWorkingHour::class);
    }

    public function doctorProfiles(): HasMany
    {
        return $this->hasMany(DoctorProfile::class);
    }

    public function securityPolicy(): HasOne
    {
        return $this->hasOne(SecurityPolicy::class);
    }

    public function brandingSetting(): HasOne
    {
        return $this->hasOne(BrandingSetting::class);
    }

    public function sensitiveAccessLogs(): HasMany
    {
        return $this->hasMany(SensitiveAccessLog::class);
    }

    public function complianceRuns(): HasMany
    {
        return $this->hasMany(ComplianceRun::class);
    }

    public function userInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function visitDiagnoses(): HasMany
    {
        return $this->hasMany(VisitDiagnosis::class);
    }

    public function visitVitalSigns(): HasMany
    {
        return $this->hasMany(VisitVitalSign::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function pharmacyDrugs(): HasMany
    {
        return $this->hasMany(PharmacyDrug::class);
    }

    public function pharmacyDispenses(): HasMany
    {
        return $this->hasMany(PharmacyDispense::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function radiologyOrders(): HasMany
    {
        return $this->hasMany(RadiologyOrder::class);
    }

    public function radiologyReports(): HasMany
    {
        return $this->hasMany(RadiologyReport::class);
    }
}
