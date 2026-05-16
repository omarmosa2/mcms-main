<?php

namespace App\Concerns;

use App\Models\Clinic;
use App\Scopes\ClinicScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToClinic
{
    public static function bootBelongsToClinic(): void
    {
        static::addGlobalScope(new ClinicScope);

        static::creating(function (self $model): void {
            if ($model->clinic_id === null && auth()->check()) {
                $model->clinic_id = auth()->user()->clinic_id;
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeWithoutClinicScope($query)
    {
        return $query->withoutGlobalScope(ClinicScope::class);
    }
}
