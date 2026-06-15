<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PatientFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Patient extends BaseModel
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'file_number' => 'integer',
        ];
    }

    public static function hashNationalId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '', $normalized);

        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    public function getNationalIdAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function setNationalIdAttribute(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->attributes['national_id'] = null;
            $this->attributes['national_id_hash'] = null;

            return;
        }

        $normalized = trim($value);

        $this->attributes['national_id'] = Crypt::encryptString($normalized);
        $this->attributes['national_id_hash'] = self::hashNationalId($normalized);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function chronicConditions(): HasMany
    {
        return $this->hasMany(PatientChronicCondition::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(PatientMedication::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PatientAttachment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function radiologyOrders(): HasMany
    {
        return $this->hasMany(RadiologyOrder::class);
    }

    public function portalTokens(): HasMany
    {
        return $this->hasMany(PatientPortalToken::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function cardVisits(): HasMany
    {
        return $this->hasMany(PatientCardVisit::class);
    }
}
