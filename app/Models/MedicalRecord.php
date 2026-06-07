<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalRecord extends BaseModel
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CLINIC_TYPE_INTERNAL_MEDICINE = 'internal_medicine';

    public const CLINIC_TYPE_PEDIATRICS = 'pediatrics';

    public const CLINIC_TYPE_GYNECOLOGY = 'gynecology';

    public const CLINIC_TYPE_ORTHOPEDICS = 'orthopedics';

    public const CLINIC_TYPE_DERMATOLOGY = 'dermatology';

    public const CLINIC_TYPE_OPTHALMOLOGY = 'ophthalmology';

    public const CLINIC_TYPE_ENT = 'ent';

    public const CLINIC_TYPE_CARDIOLOGY = 'cardiology';

    public const CLINIC_TYPE_NEUROLOGY = 'neurology';

    public const CLINIC_TYPE_PSYCHIATRY = 'psychiatry';

    public const CLINIC_TYPE_GENERAL_SURGERY = 'general_surgery';

    public const CLINIC_TYPE_UROLOGY = 'urology';

    public const CLINIC_TYPE_DENTAL = 'dental';

    public const CLINIC_TYPE_OTHER = 'other';

    public const CLINIC_TYPES = [
        self::CLINIC_TYPE_INTERNAL_MEDICINE,
        self::CLINIC_TYPE_PEDIATRICS,
        self::CLINIC_TYPE_GYNECOLOGY,
        self::CLINIC_TYPE_ORTHOPEDICS,
        self::CLINIC_TYPE_DERMATOLOGY,
        self::CLINIC_TYPE_OPTHALMOLOGY,
        self::CLINIC_TYPE_ENT,
        self::CLINIC_TYPE_CARDIOLOGY,
        self::CLINIC_TYPE_NEUROLOGY,
        self::CLINIC_TYPE_PSYCHIATRY,
        self::CLINIC_TYPE_GENERAL_SURGERY,
        self::CLINIC_TYPE_UROLOGY,
        self::CLINIC_TYPE_DENTAL,
        self::CLINIC_TYPE_OTHER,
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'visit_date' => 'date',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
