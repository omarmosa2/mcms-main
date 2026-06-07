<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\Cachable;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\DoctorProfile;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AdminSettingsController extends Controller
{
    public function clinic(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);
        $settings = ClinicSetting::getGroupSettings($clinicId, 'clinic');
        $defaults = ClinicSetting::defaults('clinic');
        $merged = array_merge($defaults, $settings);

        return Inertia::render('settings/admin/ClinicSettings', [
            'settings' => $merged,
        ]);
    }

    public function updateClinic(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'invoice_clinic_name' => ['nullable', 'string', 'max:255'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'invoice_default_notes' => ['nullable', 'string', 'max:1000'],
            'currency_syp' => ['nullable', 'numeric', 'min:0'],
            'currency_try' => ['nullable', 'numeric', 'min:0'],
            'currency_usd' => ['nullable', 'numeric', 'min:0'],
            'currency_iqd' => ['nullable', 'numeric', 'min:0'],
            'thousands_separator' => ['nullable', 'string', 'max:1'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        ClinicSetting::setGroup($clinicId, 'clinic', $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات المجمع الطبي بنجاح']);

        return to_route('admin-settings.clinic');
    }

    public function appointments(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);
        $settings = ClinicSetting::getGroupSettings($clinicId, 'appointments');
        $defaults = ClinicSetting::defaults('appointments');
        $merged = array_merge($defaults, $settings);

        return Inertia::render('settings/admin/AppointmentSettings', [
            'settings' => $merged,
        ]);
    }

    public function updateAppointments(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'default_duration' => ['required', 'integer', 'min:5', 'max:240'],
            'allow_outside_hours' => ['required', 'boolean'],
            'allow_overlapping' => ['required', 'boolean'],
            'max_per_doctor_per_day' => ['required', 'integer', 'min:1', 'max:200'],
            'types' => ['required', 'array', 'min:1'],
            'types.*.name' => ['required', 'string', 'max:100'],
            'types.*.is_default' => ['required', 'boolean'],
        ]);

        ClinicSetting::setGroup($clinicId, 'appointments', $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات المواعيد بنجاح']);

        return to_route('admin-settings.appointments');
    }

    public function financial(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);
        $settings = ClinicSetting::getGroupSettings($clinicId, 'financial');
        $defaults = ClinicSetting::defaults('financial');
        $merged = array_merge($defaults, $settings);

        return Inertia::render('settings/admin/FinancialSettings', [
            'settings' => $merged,
        ]);
    }

    public function updateFinancial(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['required', Rule::in(['cash', 'bank_transfer', 'card'])],
            'salary_generation_day' => ['required', 'integer', 'min:1', 'max:28'],
            'salary_due_date' => ['required', 'integer', 'min:1', 'max:28'],
            'doctor_earning_mode' => ['required', Rule::in(['appointment_only', 'appointment_and_procedures'])],
            'currency_display_format' => ['required', Rule::in(['symbol', 'code', 'name'])],
            'rounding_rule' => ['required', Rule::in(['none', 'round_up', 'round_down', 'round_nearest'])],
        ]);

        ClinicSetting::setGroup($clinicId, 'financial', $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حفظ الإعدادات المالية بنجاح']);

        return to_route('admin-settings.financial');
    }

    public function permissions(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);

        $roles = Role::query()
            ->where('clinic_id', $clinicId)
            ->with('permissions')
            ->get();

        $allPermissions = Permission::query()
            ->where('clinic_id', $clinicId)
            ->pluck('name')
            ->filter(fn (string $name) => ! str_contains($name, '*'))
            ->values()
            ->all();

        $rolesData = $roles->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description ?? null,
            'is_system' => $role->is_system ?? false,
            'permissions' => $role->permissions->pluck('name')->all(),
        ])->all();

        return Inertia::render('settings/admin/PermissionsSettings', [
            'roles' => $rolesData,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function updatePermissions(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*.id' => ['required', 'integer', 'exists:roles,id'],
            'roles.*.permissions' => ['required', 'array'],
            'roles.*.permissions.*' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($clinicId, $validated): void {
            foreach ($validated['roles'] as $roleData) {
                $role = Role::query()->where('id', $roleData['id'])->where('clinic_id', $clinicId)->firstOrFail();

                $permissionIds = Permission::query()
                    ->where('clinic_id', $clinicId)
                    ->whereIn('name', $roleData['permissions'])
                    ->pluck('id')
                    ->all();

                $syncData = [];
                foreach ($permissionIds as $permissionId) {
                    $syncData[$permissionId] = ['clinic_id' => $clinicId];
                }

                $role->permissions()->sync($syncData);
            }
        });

        Cachable::clearClinicCache($clinicId);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حفظ الصلاحيات بنجاح']);

        return to_route('admin-settings.permissions');
    }

    public function appearance(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);
        $settings = ClinicSetting::getGroupSettings($clinicId, 'appearance');
        $defaults = ClinicSetting::defaults('appearance');
        $merged = array_merge($defaults, $settings);

        return Inertia::render('settings/admin/AdminAppearance', [
            'settings' => $merged,
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'language' => ['required', Rule::in(['ar', 'en'])],
            'font_size' => ['required', Rule::in(['small', 'medium', 'large'])],
        ]);

        ClinicSetting::setGroup($clinicId, 'appearance', $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات المظهر بنجاح']);

        return to_route('admin-settings.appearance');
    }

    public function security(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);

        $recentLogs = AuditLog::query()
            ->where('clinic_id', $clinicId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'user_id', 'action', 'description', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user_name' => $log->user?->name ?? 'النظام',
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at?->diffForHumans(),
            ])
            ->all();

        return Inertia::render('settings/admin/SecuritySettings', [
            'activityLogs' => $recentLogs,
        ]);
    }

    public function diagnostics(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);

        $dbStatus = 'connected';
        $dbSize = 'N/A';
        $tableCount = count(Schema::getTables());

        try {
            $dbSizeResult = DB::select('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?', [DB::getDatabaseName()]);
            $dbSize = ($dbSizeResult[0]->size_mb ?? 'N/A').' MB';
        } catch (\Throwable) {
            $dbSize = 'N/A';
        }

        $diagnostics = [
            'database' => [
                'status' => $dbStatus,
                'name' => DB::getDatabaseName(),
                'size' => $dbSize,
                'table_count' => $tableCount,
            ],
            'application' => [
                'version' => ClinicSetting::get($clinicId, 'support', 'app_version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'user_count' => User::query()->where('clinic_id', $clinicId)->count(),
                'doctor_count' => DoctorProfile::query()->where('clinic_id', $clinicId)->count(),
                'patient_count' => Patient::query()->where('clinic_id', $clinicId)->count(),
                'employee_count' => Employee::query()->where('clinic_id', $clinicId)->count(),
            ],
            'performance' => [
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2).' MB',
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2).' MB',
            ],
        ];

        return Inertia::render('settings/admin/DiagnosticsSettings', [
            'diagnostics' => $diagnostics,
        ]);
    }

    public function support(Request $request): Response
    {
        $clinicId = $this->resolveClinicId($request);
        $settings = ClinicSetting::getGroupSettings($clinicId, 'support');
        $defaults = ClinicSetting::defaults('support');
        $merged = array_merge($defaults, $settings);

        return Inertia::render('settings/admin/SupportSettings', [
            'settings' => $merged,
        ]);
    }

    public function updateSupport(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'support_hours' => ['nullable', 'string', 'max:255'],
            'license_info' => ['nullable', 'string', 'max:500'],
        ]);

        ClinicSetting::setGroup($clinicId, 'support', $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'تم حفظ معلومات الدعم بنجاح']);

        return to_route('admin-settings.support');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
