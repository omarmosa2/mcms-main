<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends BaseModel
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory, SoftDeletes;

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withPivot(['clinic_id'])
            ->withTimestamps();
    }
}
