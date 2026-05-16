<?php

namespace App\Models;

use App\Concerns\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityPolicy extends Model
{
    use Cachable, HasFactory;

    protected string $cachePrefix = 'security_policy';

    protected $table = 'security_policies';

    protected $fillable = [
        'clinic_id',
        'updated_by',
        'password_min_length',
        'require_mixed_case',
        'require_numbers',
        'require_symbols',
        'force_two_factor',
        'confirm_password_for_security_actions',
        'audit_retention_days',
        'sensitive_access_retention_days',
        'session_lifetime_minutes',
        'idle_timeout_minutes',
    ];

    protected $casts = [
        'clinic_id' => 'integer',
        'updated_by' => 'integer',
        'password_min_length' => 'integer',
        'require_mixed_case' => 'boolean',
        'require_numbers' => 'boolean',
        'require_symbols' => 'boolean',
        'force_two_factor' => 'boolean',
        'confirm_password_for_security_actions' => 'boolean',
        'audit_retention_days' => 'integer',
        'sensitive_access_retention_days' => 'integer',
        'session_lifetime_minutes' => 'integer',
        'idle_timeout_minutes' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }
}
