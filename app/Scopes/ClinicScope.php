<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClinicScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($model->clinic_id !== null) {
            $builder->where($model->getTable().'.clinic_id', $model->clinic_id);
        }
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutClinicScope', function (Builder $builder): Builder {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
