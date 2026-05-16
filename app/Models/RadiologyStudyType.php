<?php

namespace App\Models;

use App\Concerns\Cachable;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\RadiologyStudyTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadiologyStudyType extends BaseModel
{
    /** @use HasFactory<RadiologyStudyTypeFactory> */
    use Cachable, HasFactory, SoftDeletes;

    protected string $cachePrefix = 'radiology_study_types';

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'requires_contrast' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequiresContrast($query)
    {
        return $query->where('requires_contrast', true);
    }
}
