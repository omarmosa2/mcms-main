<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Shared\Traits\HasClinic;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['clinic_id', 'name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasClinic, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * @var array<int, Collection<int, string>>
     */
    private array $roleNamesByClinic = [];

    /**
     * @var array<int, Collection<int, string>>
     */
    private array $permissionNamesByClinic = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clinic_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function doctorAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function createdAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function doctorSchedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_id');
    }

    public function issuedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'issued_by');
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function securityPoliciesUpdated(): HasMany
    {
        return $this->hasMany(SecurityPolicy::class, 'updated_by');
    }

    public function sensitiveAccessLogs(): HasMany
    {
        return $this->hasMany(SensitiveAccessLog::class);
    }

    public function complianceRuns(): HasMany
    {
        return $this->hasMany(ComplianceRun::class, 'ran_by');
    }

    public function orderedPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'ordered_by');
    }

    public function receivedPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'received_by');
    }

    public function resolvedInventoryAlerts(): HasMany
    {
        return $this->hasMany(InventoryAlert::class, 'resolved_by');
    }

    public function createdPortalTokens(): HasMany
    {
        return $this->hasMany(PatientPortalToken::class, 'created_by');
    }

    public function patientCardVisits(): HasMany
    {
        return $this->hasMany(PatientCardVisit::class, 'doctor_id');
    }

    public function uploadedRadiologyImages(): HasMany
    {
        return $this->hasMany(RadiologyImage::class, 'uploaded_by');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'invited_by');
    }

    public function acceptedInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'accepted_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['clinic_id', 'assigned_by'])
            ->withTimestamps();
    }

    public function permissions(): Builder
    {
        return Permission::query()
            ->select('permissions.*')
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->join('role_user', function ($join): void {
                $join->on('role_user.role_id', '=', 'permission_role.role_id')
                    ->where('role_user.user_id', $this->id);
            })
            ->where('permissions.clinic_id', $this->clinic_id)
            ->where('permission_role.clinic_id', $this->clinic_id)
            ->where('role_user.clinic_id', $this->clinic_id)
            ->distinct();
    }

    public function hasRole(string $roleName): bool
    {
        if ($this->clinic_id === null) {
            return false;
        }

        return $this->roleNamesForCurrentClinic()->contains($roleName);
    }

    public function assignRole(Role $role, ?int $assignedBy = null): void
    {
        if ($this->clinic_id === null || $role->clinic_id !== $this->clinic_id) {
            return;
        }

        $this->roles()->syncWithoutDetaching([
            $role->id => [
                'clinic_id' => $this->clinic_id,
                'assigned_by' => $assignedBy,
            ],
        ]);

        unset($this->roleNamesByClinic[(int) $this->clinic_id]);
        $this->invalidatePermissionCache();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->clinic_id === null) {
            return false;
        }

        if ($this->roleNamesForCurrentClinic()->contains('super_admin')) {
            return true;
        }

        $permissionNames = $this->getCachedPermissions();

        foreach ($permissionNames as $permissionName) {
            if ($this->permissionMatches($permissionName, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function getCachedPermissions(): Collection
    {
        if ($this->clinic_id === null) {
            return collect();
        }

        $clinicId = (int) $this->clinic_id;

        if (array_key_exists($clinicId, $this->permissionNamesByClinic)) {
            return $this->permissionNamesByClinic[$clinicId];
        }

        $key = "clinic:{$clinicId}:user:{$this->id}:permissions";

        $cached = Cache::get($key);

        if ($cached instanceof \__PHP_Incomplete_Class || $cached instanceof Collection) {
            Cache::forget($key);
            $cached = null;
        }

        if (is_array($cached)) {
            $this->permissionNamesByClinic[$clinicId] = collect($cached)
                ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
                ->values();

            return $this->permissionNamesByClinic[$clinicId];
        }

        $permissions = $this->permissions()->pluck('permissions.name')->toArray();

        Cache::put($key, $permissions, now()->addSeconds(900));

        $this->permissionNamesByClinic[$clinicId] = collect($permissions);

        return $this->permissionNamesByClinic[$clinicId];
    }

    public function invalidatePermissionCache(): void
    {
        if ($this->clinic_id !== null) {
            Cache::forget("clinic:{$this->clinic_id}:user:{$this->id}:permissions");
            unset($this->permissionNamesByClinic[(int) $this->clinic_id]);
        }
    }

    public function isClinicSecurityManager(): bool
    {
        return $this->roleNamesForCurrentClinic()
            ->intersect(['super_admin', 'admin', 'clinic_admin'])
            ->isNotEmpty();
    }

    public function roleNamesForCurrentClinic(): Collection
    {
        if ($this->clinic_id === null) {
            return collect();
        }

        $clinicId = (int) $this->clinic_id;

        if (! array_key_exists($clinicId, $this->roleNamesByClinic)) {
            $this->roleNamesByClinic[$clinicId] = $this->roles()
                ->where('roles.clinic_id', $clinicId)
                ->pluck('roles.name')
                ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
                ->values();
        }

        return $this->roleNamesByClinic[$clinicId];
    }

    private function permissionMatches(string $grantedPermission, string $requestedPermission): bool
    {
        if ($grantedPermission === $requestedPermission) {
            return true;
        }

        if (str_ends_with($grantedPermission, '.*')) {
            $prefix = substr($grantedPermission, 0, -1);

            return str_starts_with($requestedPermission, $prefix);
        }

        return false;
    }

    public function prefersEmailNotification(string $type): bool
    {
        $preferences = $this->notification_preferences ?? [];

        return ($preferences[$type]['email'] ?? true) === true;
    }

    public function prefersSmsNotification(string $type): bool
    {
        $preferences = $this->notification_preferences ?? [];

        return ($preferences[$type]['sms'] ?? false) === true;
    }

    /**
     * @return array<string, array{email: bool, sms: bool}>
     */
    public function getDefaultNotificationPreferences(): array
    {
        return [
            'appointment_reminder' => ['email' => true, 'sms' => false],
            'invoice_issued' => ['email' => true, 'sms' => false],
            'prescription_ready' => ['email' => true, 'sms' => false],
        ];
    }
}
