<?php

namespace App\Models;

use App\Concerns\Cachable;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends BaseModel
{
    /** @use HasFactory<DepartmentFactory> */
    use Cachable, HasFactory, SoftDeletes;

    protected string $cachePrefix = 'departments';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function doctorProfiles(): HasMany
    {
        return $this->hasMany(DoctorProfile::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(ClinicWorkingHour::class);
    }
}
