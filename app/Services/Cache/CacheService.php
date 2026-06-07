<?php

namespace App\Services\Cache;

use App\Models\Appointment;
use App\Models\BrandingSetting;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Role;
use App\Models\SecurityPolicy;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheService
{
    private const SECURITY_POLICY_TTL = 1800;

    private const USER_PERMISSIONS_TTL = 900;

    private const REFERENCE_DATA_TTL = 600;

    private const DASHBOARD_STATS_TTL = 300;

    private const BRANDING_TTL = 1800;

    private const DROPDOWN_OPTIONS_TTL = 120;

    public function getSecurityPolicy(int $clinicId): ?SecurityPolicy
    {
        $key = "clinic:{$clinicId}:security_policy";

        $attributes = $this->rememberArray($key, self::SECURITY_POLICY_TTL, function () use ($clinicId): ?array {
            try {
                return SecurityPolicy::query()
                    ->forClinic($clinicId)
                    ->first()
                    ?->getAttributes();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'no such table: security_policies')) {
                    return null;
                }

                throw $e;
            }
        });

        /** @var SecurityPolicy|null $policy */
        $policy = $this->hydrateModel(SecurityPolicy::class, $attributes);

        return $policy;
    }

    public function invalidateSecurityPolicy(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:security_policy");
    }

    public function getUserPermissions(int $userId, int $clinicId): Collection
    {
        $key = "clinic:{$clinicId}:user:{$userId}:permissions";

        $cached = Cache::get($key);

        if ($this->isUnsafeCachedValue($cached)) {
            Cache::forget($key);
            $cached = null;
        }

        if (is_array($cached)) {
            return collect($cached)
                ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
                ->values();
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            Cache::put($key, [], now()->addSeconds(self::USER_PERMISSIONS_TTL));

            return collect();
        }

        $permissions = $user->permissions()
            ->pluck('permissions.name')
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->values()
            ->all();

        Cache::put($key, $permissions, now()->addSeconds(self::USER_PERMISSIONS_TTL));

        return collect($permissions);
    }

    public function invalidateUserPermissions(int $userId, int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:user:{$userId}:permissions");
    }

    public function invalidateAllUserPermissions(int $clinicId): void
    {
        User::query()
            ->forClinic($clinicId)
            ->each(function (User $user) use ($clinicId) {
                $this->invalidateUserPermissions($user->id, $clinicId);
            });
    }

    public function getClinicRoles(int $clinicId): EloquentCollection
    {
        $key = "clinic:{$clinicId}:roles:list";

        $rows = $this->rememberList($key, self::REFERENCE_DATA_TTL, function () use ($clinicId): array {
            return Role::query()
                ->forClinic($clinicId)
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => $role->getAttributes())
                ->all();
        });

        return Role::hydrate($rows);
    }

    public function invalidateClinicRoles(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:roles");
        Cache::forget("clinic:{$clinicId}:roles:list");
    }

    public function getClinicDepartments(int $clinicId): EloquentCollection
    {
        $key = "clinic:{$clinicId}:departments:list";

        $rows = $this->rememberList($key, self::REFERENCE_DATA_TTL, function () use ($clinicId): array {
            return Department::query()
                ->forClinic($clinicId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => $department->getAttributes())
                ->all();
        });

        return Department::hydrate($rows);
    }

    public function invalidateClinicDepartments(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:departments");
        Cache::forget("clinic:{$clinicId}:departments:list");
    }

    public function getClinicExpenseCategories(int $clinicId): EloquentCollection
    {
        $key = "clinic:{$clinicId}:expense_categories:list";

        $rows = $this->rememberList($key, self::REFERENCE_DATA_TTL, function () use ($clinicId): array {
            return ExpenseCategory::query()
                ->forClinic($clinicId)
                ->orderBy('name')
                ->get()
                ->map(fn (ExpenseCategory $category): array => $category->getAttributes())
                ->all();
        });

        return ExpenseCategory::hydrate($rows);
    }

    public function invalidateClinicExpenseCategories(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:expense_categories");
        Cache::forget("clinic:{$clinicId}:expense_categories:list");
    }

    public function getDashboardStats(int $clinicId): array
    {
        $key = "clinic:{$clinicId}:dashboard_stats";

        $stats = Cache::get($key);

        if ($stats instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $stats = null;
        }

        if (is_array($stats)) {
            return $stats;
        }

        return Cache::remember($key, now()->addSeconds(self::DASHBOARD_STATS_TTL), function () use ($clinicId) {
            $driver = DB::getDriverName();

            $monthExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at) as month"
                : "DATE_FORMAT(created_at, '%Y-%m') as month";

            $dayExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m-%d', created_at) as day"
                : 'DATE(created_at) as day';

            $today = today();
            $now = now();
            $oneHourLater = $now->copy()->addHour();

            $todayAppointments = Appointment::query()
                ->forClinic($clinicId)
                ->whereDate('scheduled_for', $today);

            $upcomingAppointments = (clone $todayAppointments)
                ->whereBetween('scheduled_for', [$now, $oneHourLater])
                ->with(['patient', 'doctor'])
                ->orderBy('scheduled_for')
                ->get();

            $pendingInvoicesToday = Invoice::query()
                ->forClinic($clinicId)
                ->whereDate('created_at', $today)
                ->where('status', 'unpaid');

            return [
                'total_patients' => Patient::query()->forClinic($clinicId)->withoutTrashed()->count(),
                'today_new_patients' => Patient::query()
                    ->forClinic($clinicId)
                    ->withoutTrashed()
                    ->whereDate('created_at', $today)
                    ->count(),
                'today_appointments' => (clone $todayAppointments)->count(),
                'today_appointments_by_status' => (clone $todayAppointments)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),
                'pending_invoices_today' => (clone $pendingInvoicesToday)->count(),
                'pending_invoices_amount_today' => (clone $pendingInvoicesToday)->sum('total_amount'),
                'upcoming_appointments' => $upcomingAppointments->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'time' => $appointment->scheduled_for?->format('H:i'),
                        'patient_name' => trim($appointment->patient?->first_name.' '.$appointment->patient?->last_name),
                        'doctor_name' => $appointment->doctor?->name,
                        'status' => $appointment->status,
                    ];
                })->values()->all(),
                'patients_by_month' => Patient::query()
                    ->forClinic($clinicId)
                    ->selectRaw($monthExpr.', COUNT(*) as count')
                    ->where('created_at', '>=', now()->subMonths(6))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('count', 'month')
                    ->toArray(),
                'appointments_by_status' => Appointment::query()
                    ->forClinic($clinicId)
                    ->where('scheduled_for', '>=', now()->subDays(30))
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),
                'revenue_by_month' => Invoice::query()
                    ->forClinic($clinicId)
                    ->selectRaw($monthExpr.', SUM(total_amount) as total')
                    ->where('created_at', '>=', now()->subMonths(6))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month')
                    ->toArray(),
                'last_7_days_revenue' => Invoice::query()
                    ->forClinic($clinicId)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->selectRaw($dayExpr.', SUM(total_amount) as total')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('total', 'day')
                    ->toArray(),
                'last_7_days_patients' => Patient::query()
                    ->forClinic($clinicId)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->selectRaw($dayExpr.', COUNT(*) as count')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('count', 'day')
                    ->toArray(),
            ];
        });
    }

    public function invalidateDashboardStats(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:dashboard_stats");
    }

    public function getBrandingSettings(int $clinicId): ?BrandingSetting
    {
        $key = "clinic:{$clinicId}:branding";

        $attributes = $this->rememberArray($key, self::BRANDING_TTL, function () use ($clinicId): ?array {
            try {
                return BrandingSetting::query()
                    ->forClinic($clinicId)
                    ->first()
                    ?->getAttributes();
            } catch (QueryException) {
                return null;
            }
        });

        /** @var BrandingSetting|null $branding */
        $branding = $this->hydrateModel(BrandingSetting::class, $attributes);

        return $branding;
    }

    public function invalidateBrandingSettings(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:branding");
    }

    public function getPatientsDropdown(int $clinicId): array
    {
        $key = "clinic:{$clinicId}:dropdown:patients";

        $patients = Cache::get($key);

        if ($this->isUnsafeCachedValue($patients)) {
            Cache::forget($key);
            $patients = null;
        }

        if (is_array($patients)) {
            return $patients;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId) {
            return Patient::query()
                ->forClinic($clinicId)
                ->select(['id', 'first_name', 'last_name', 'file_number', 'phone', 'date_of_birth'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(200)
                ->get()
                ->map(fn (Patient $patient): array => [
                    'id' => $patient->id,
                    'full_name' => trim("{$patient->first_name} {$patient->last_name}"),
                    'file_number' => $patient->file_number,
                    'phone' => $patient->phone,
                    'date_of_birth' => $patient->date_of_birth?->toDateString(),
                    'age' => $patient->date_of_birth?->age,
                ])
                ->values()
                ->all();
        });
    }

    public function getDoctorsDropdown(int $clinicId): array
    {
        $key = "clinic:{$clinicId}:dropdown:doctors";

        $doctors = Cache::get($key);

        if ($this->isUnsafeCachedValue($doctors)) {
            Cache::forget($key);
            $doctors = null;
        }

        if (is_array($doctors)) {
            return $doctors;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId) {
            return User::query()
                ->where('clinic_id', $clinicId)
                ->with(['doctorProfile.department:id,clinic_id,name'])
                ->whereHas('roles', function ($builder) use ($clinicId): void {
                    $builder
                        ->where('roles.clinic_id', $clinicId)
                        ->where('roles.name', 'doctor');
                })
                ->select(['id', 'name'])
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (User $doctor): array => [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'department_id' => $doctor->doctorProfile?->department_id,
                    'specialty' => $doctor->doctorProfile?->specialty,
                    'department' => $doctor->doctorProfile?->department !== null
                        ? [
                            'id' => $doctor->doctorProfile->department->id,
                            'name' => $doctor->doctorProfile->department->name,
                        ]
                        : null,
                ])
                ->values()
                ->all();
        });
    }

    public function getAppointmentsDropdown(int $clinicId, ?int $doctorId = null): array
    {
        $doctorKey = $doctorId !== null ? ":doctor{$doctorId}" : '';
        $key = "clinic:{$clinicId}:dropdown:appointments{$doctorKey}";

        $appointments = Cache::get($key);

        if ($this->isUnsafeCachedValue($appointments)) {
            Cache::forget($key);
            $appointments = null;
        }

        if (is_array($appointments)) {
            return $appointments;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId, $doctorId) {
            $query = Appointment::query()
                ->forClinic($clinicId)
                ->select(['id', 'appointment_number'])
                ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
                ->orderByDesc('scheduled_for')
                ->limit(200);

            if ($doctorId !== null) {
                $query->where('doctor_id', $doctorId);
            }

            return $query->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'appointment_number' => $appointment->appointment_number,
                ])
                ->values()
                ->all();
        });
    }

    public function invalidateDropdowns(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:dropdown:patients");
        Cache::forget("clinic:{$clinicId}:dropdown:doctors");
        Cache::forget("clinic:{$clinicId}:dropdown:appointments");

        User::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', function ($builder) use ($clinicId): void {
                $builder
                    ->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->select(['id'])
            ->each(function (User $doctor) use ($clinicId): void {
                Cache::forget("clinic:{$clinicId}:dropdown:appointments:doctor{$doctor->id}");
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rememberArray(string $key, int $ttlSeconds, Closure $callback): ?array
    {
        $cached = Cache::get($key);

        if ($this->isUnsafeCachedValue($cached)) {
            Cache::forget($key);
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            Cache::put($key, $value, now()->addSeconds($ttlSeconds));
        }

        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rememberList(string $key, int $ttlSeconds, Closure $callback): array
    {
        $cached = Cache::get($key);

        if ($this->isUnsafeCachedValue($cached)) {
            Cache::forget($key);
            $cached = null;
        }

        if (is_array($cached) && $this->isListOfArrays($cached)) {
            return array_values($cached);
        }

        $value = $callback();

        Cache::put($key, $value, now()->addSeconds($ttlSeconds));

        return $value;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>|null  $attributes
     */
    private function hydrateModel(string $modelClass, ?array $attributes): ?Model
    {
        if ($attributes === null) {
            return null;
        }

        $model = new $modelClass;
        $model->forceFill($attributes);
        $model->exists = true;

        return $model;
    }

    private function isUnsafeCachedValue(mixed $value): bool
    {
        return $value instanceof \__PHP_Incomplete_Class
            || $value instanceof Model
            || $value instanceof Collection
            || $value instanceof EloquentCollection;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isListOfArrays(array $value): bool
    {
        foreach ($value as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }
}
