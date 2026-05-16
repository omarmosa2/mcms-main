<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationAttempts;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityPolicy;
use App\Observers\ClinicObserver;
use App\Observers\DepartmentObserver;
use App\Observers\ExpenseCategoryObserver;
use App\Observers\PermissionObserver;
use App\Observers\RoleObserver;
use App\Observers\SecurityPolicyObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerObservers();
        $this->registerAuthenticationEventListeners();
        Schema::defaultStringLength(191);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerObservers(): void
    {
        Clinic::observe(ClinicObserver::class);
        SecurityPolicy::observe(SecurityPolicyObserver::class);
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
        Department::observe(DepartmentObserver::class);
        ExpenseCategory::observe(ExpenseCategoryObserver::class);
    }

    protected function registerAuthenticationEventListeners(): void
    {
        Event::listen(Login::class, [LogAuthenticationAttempts::class, 'handle']);
        Event::listen(Failed::class, [LogAuthenticationAttempts::class, 'handle']);
        Event::listen(Lockout::class, [LogAuthenticationAttempts::class, 'handle']);
    }
}
