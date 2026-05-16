<?php

namespace App\Models;

use App\Concerns\Cachable;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\LabTestTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplate extends BaseModel
{
    /** @use HasFactory<LabTestTemplateFactory> */
    use Cachable, HasFactory, SoftDeletes;

    protected string $cachePrefix = 'lab_test_templates';

    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'min_reference' => 'decimal:2',
            'max_reference' => 'decimal:2',
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

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isWithinReferenceRange(float $value): bool
    {
        if ($this->min_reference === null || $this->max_reference === null) {
            return true;
        }

        return $value >= (float) $this->min_reference && $value <= (float) $this->max_reference;
    }
}
