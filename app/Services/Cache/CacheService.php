<?php

namespace App\Services\Cache;

use App\Models\Appointment;
use App\Models\BrandingSetting;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\SecurityPolicy;
use App\Models\User;
use App\Models\Visit;
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

        $policy = Cache::get($key);

        if ($policy instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $policy = null;
        }

        if ($policy !== null) {
            return $policy;
        }

        try {
            $policy = SecurityPolicy::query()
                ->forClinic($clinicId)
                ->first();
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'no such table: security_policies')) {
                return null;
            }
            throw $e;
        }

        if ($policy !== null) {
            Cache::put($key, $policy, now()->addSeconds(self::SECURITY_POLICY_TTL));
        }

        return $policy;
    }

    public function invalidateSecurityPolicy(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:security_policy");
    }

    public function getUserPermissions(int $userId, int $clinicId): Collection
    {
        $key = "clinic:{$clinicId}:user:{$userId}:permissions";

        $permissions = Cache::get($key);

        if ($permissions instanceof \__PHP_Incomplete_Class || ($permissions instanceof Collection && $permissions->first() instanceof \__PHP_Incomplete_Class)) {
            Cache::forget($key);
            $permissions = null;
        }

        if ($permissions instanceof Collection) {
            return $permissions;
        }

        if (is_array($permissions)) {
            return collect($permissions);
        }

        return Cache::remember($key, now()->addSeconds(self::USER_PERMISSIONS_TTL), function () use ($userId) {
            $user = User::query()->find($userId);

            if ($user === null) {
                return collect();
            }

            return $user->permissions()->pluck('permissions.name');
        });
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

    public function getClinicRoles(int $clinicId): Collection
    {
        $key = "clinic:{$clinicId}:roles:list";

        $roles = Cache::get($key);

        if ($roles instanceof \__PHP_Incomplete_Class || ($roles instanceof Collection && $roles->first() instanceof \__PHP_Incomplete_Class)) {
            Cache::forget($key);
            $roles = null;
        }

        if ($roles instanceof Collection) {
            return $roles;
        }

        if (is_array($roles)) {
            return collect($roles);
        }

        return Cache::remember($key, now()->addSeconds(self::REFERENCE_DATA_TTL), function () use ($clinicId) {
            return Role::query()
                ->forClinic($clinicId)
                ->orderBy('name')
                ->get();
        });
    }

    public function invalidateClinicRoles(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:roles");
        Cache::forget("clinic:{$clinicId}:roles:list");
    }

    public function getClinicDepartments(int $clinicId): Collection
    {
        $key = "clinic:{$clinicId}:departments:list";

        $departments = Cache::get($key);

        if ($departments instanceof \__PHP_Incomplete_Class || ($departments instanceof Collection && $departments->first() instanceof \__PHP_Incomplete_Class)) {
            Cache::forget($key);
            $departments = null;
        }

        if ($departments instanceof Collection) {
            return $departments;
        }

        if (is_array($departments)) {
            return collect($departments);
        }

        return Cache::remember($key, now()->addSeconds(self::REFERENCE_DATA_TTL), function () use ($clinicId) {
            return Department::query()
                ->forClinic($clinicId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        });
    }

    public function invalidateClinicDepartments(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:departments");
        Cache::forget("clinic:{$clinicId}:departments:list");
    }

    public function getClinicExpenseCategories(int $clinicId): Collection
    {
        $key = "clinic:{$clinicId}:expense_categories:list";

        $categories = Cache::get($key);

        if ($categories instanceof \__PHP_Incomplete_Class || ($categories instanceof Collection && $categories->first() instanceof \__PHP_Incomplete_Class)) {
            Cache::forget($key);
            $categories = null;
        }

        if ($categories instanceof Collection) {
            return $categories;
        }

        if (is_array($categories)) {
            return collect($categories);
        }

        return Cache::remember($key, now()->addSeconds(self::REFERENCE_DATA_TTL), function () use ($clinicId) {
            return ExpenseCategory::query()
                ->forClinic($clinicId)
                ->orderBy('name')
                ->get();
        });
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

            $todayQueueEntries = QueueEntry::query()
                ->forClinic($clinicId)
                ->where('queue_date', $today);

            $longWaitingPatients = (clone $todayQueueEntries)
                ->where('status', 'waiting')
                ->where('created_at', '<=', $now->copy()->subMinutes(30))
                ->with(['patient'])
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
                'pending_queue' => (clone $todayQueueEntries)->where('status', 'waiting')->count(),
                'active_visits' => Visit::query()
                    ->forClinic($clinicId)
                    ->whereIn('status', ['started', 'in_progress'])
                    ->count(),
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
                'long_waiting_patients' => $longWaitingPatients->map(function ($entry) use ($now) {
                    return [
                        'id' => $entry->id,
                        'queue_number' => $entry->queue_number,
                        'patient_name' => trim($entry->patient?->first_name.' '.$entry->patient?->last_name),
                        'waiting_minutes' => $entry->created_at?->diffInMinutes($now) ?? 0,
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
                'visits_by_month' => Visit::query()
                    ->forClinic($clinicId)
                    ->selectRaw($monthExpr.', COUNT(*) as count')
                    ->where('created_at', '>=', now()->subMonths(6))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('count', 'month')
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

        $branding = Cache::get($key);

        if ($branding instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $branding = null;
        }

        if ($branding instanceof BrandingSetting) {
            return $branding;
        }

        return Cache::remember($key, now()->addSeconds(self::BRANDING_TTL), function () use ($clinicId) {
            try {
                return BrandingSetting::query()
                    ->forClinic($clinicId)
                    ->first();
            } catch (QueryException) {
                return null;
            }
        });
    }

    public function invalidateBrandingSettings(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:branding");
    }

    public function getPatientsDropdown(int $clinicId): array
    {
        $key = "clinic:{$clinicId}:dropdown:patients";

        $patients = Cache::get($key);

        if ($patients instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $patients = null;
        }

        if (is_array($patients)) {
            return $patients;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId) {
            return Patient::query()
                ->forClinic($clinicId)
                ->select(['id', 'first_name', 'last_name'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(200)
                ->get()
                ->map(fn (Patient $patient): array => [
                    'id' => $patient->id,
                    'full_name' => trim("{$patient->first_name} {$patient->last_name}"),
                ])
                ->values()
                ->all();
        });
    }

    public function getDoctorsDropdown(int $clinicId): array
    {
        $key = "clinic:{$clinicId}:dropdown:doctors";

        $doctors = Cache::get($key);

        if ($doctors instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $doctors = null;
        }

        if (is_array($doctors)) {
            return $doctors;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId) {
            return User::query()
                ->where('clinic_id', $clinicId)
                ->whereHas('roles', function ($builder) use ($clinicId): void {
                    $builder
                        ->where('roles.clinic_id', $clinicId)
                        ->where('roles.name', 'doctor');
                })
                ->select(['id', 'name'])
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->all();
        });
    }

    public function getAppointmentsDropdown(int $clinicId, ?int $doctorId = null): array
    {
        $doctorKey = $doctorId !== null ? ":doctor{$doctorId}" : '';
        $key = "clinic:{$clinicId}:dropdown:appointments{$doctorKey}";

        $appointments = Cache::get($key);

        if ($appointments instanceof \__PHP_Incomplete_Class) {
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
                ->orderByDesc('scheduled_for')
                ->limit(200);

            if ($doctorId !== null) {
                $query->where('doctor_id', $doctorId);
            }

            return $query->get()->all();
        });
    }

    public function getQueueEntriesDropdown(int $clinicId, ?int $doctorId = null): array
    {
        $doctorKey = $doctorId !== null ? ":doctor{$doctorId}" : '';
        $key = "clinic:{$clinicId}:dropdown:queue{$doctorKey}";

        $queueEntries = Cache::get($key);

        if ($queueEntries instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $queueEntries = null;
        }

        if (is_array($queueEntries)) {
            return $queueEntries;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId, $doctorId) {
            $query = QueueEntry::query()
                ->forClinic($clinicId)
                ->select(['id', 'queue_number', 'queue_date', 'status'])
                ->whereIn('status', [
                    QueueEntry::STATUS_CALLED,
                    QueueEntry::STATUS_IN_SERVICE,
                ])
                ->orderByDesc('queue_date')
                ->orderBy('queue_number')
                ->limit(200);

            if ($doctorId !== null) {
                $query->where(function ($builder) use ($doctorId): void {
                    $builder
                        ->where('assigned_doctor_id', $doctorId)
                        ->orWhereHas('visit', function ($visitQuery) use ($doctorId): void {
                            $visitQuery->where('doctor_id', $doctorId);
                        });
                });
            }

            return $query->get()
                ->map(fn (QueueEntry $entry): array => [
                    'id' => $entry->id,
                    'label' => sprintf('#%s - %s (%s)', $entry->queue_number, $entry->queue_date?->toDateString(), $entry->status),
                ])
                ->values()
                ->all();
        });
    }

    public function getVisitsDropdown(int $clinicId): array
    {
        $key = "clinic:{$clinicId}:dropdown:visits";

        $visits = Cache::get($key);

        if ($visits instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $visits = null;
        }

        if (is_array($visits)) {
            return $visits;
        }

        return Cache::remember($key, now()->addSeconds(self::DROPDOWN_OPTIONS_TTL), function () use ($clinicId) {
            return Visit::query()
                ->forClinic($clinicId)
                ->select(['id', 'visit_number'])
                ->orderByDesc('started_at')
                ->limit(200)
                ->get()
                ->all();
        });
    }

    public function invalidateDropdowns(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:dropdown:patients");
        Cache::forget("clinic:{$clinicId}:dropdown:doctors");
        Cache::forget("clinic:{$clinicId}:dropdown:appointments");
        Cache::forget("clinic:{$clinicId}:dropdown:queue");
        Cache::forget("clinic:{$clinicId}:dropdown:visits");
    }
}
