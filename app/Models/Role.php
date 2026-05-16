<?php

namespace App\Models;

use App\Concerns\Cachable;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends BaseModel
{
    /** @use HasFactory<RoleFactory> */
    use Cachable, HasFactory, SoftDeletes;

    protected string $cachePrefix = 'roles';

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withPivot(['clinic_id'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot(['clinic_id', 'assigned_by'])
            ->withTimestamps();
    }
}
