<?php

namespace App\Domain\Shared\Traits;

use App\Models\Clinic;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasClinic
{
    public static function bootHasClinic(): void
    {
        static::addGlobalScope('clinic', function (Builder $builder): void {
            $model = $builder->getModel();

            if ($model instanceof Role || $model instanceof User) {
                return;
            }

            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($user->clinic_id !== null) {
                $table = $model->getTable();
                $builder->where("{$table}.clinic_id", $user->clinic_id);
            }
        });

        static::creating(function (self $model): void {
            if ($model->clinic_id === null && auth()->check()) {
                $model->clinic_id = auth()->user()->clinic_id;
            }
        });
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->withoutGlobalScope('clinic')->where("{$query->getModel()->getTable()}.clinic_id", $clinicId);
    }

    public function scopeWithoutClinicScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('clinic');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public static function getCurrentClinicId(): ?int
    {
        if (auth()->check()) {
            return auth()->user()->clinic_id;
        }

        return null;
    }
}
