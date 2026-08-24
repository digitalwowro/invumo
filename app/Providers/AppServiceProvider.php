<?php

namespace App\Providers;

use App\Foundation\Diagnostics\ApplicationHealth;
use App\Foundation\Tenancy\Contracts\VerifiesTenantMembership;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Queries\CompanyMembershipVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            VerifiesTenantMembership::class,
            CompanyMembershipVerifier::class,
        );
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            DiagnosingHealth::class,
            fn () => app(ApplicationHealth::class)->diagnose(),
        );
        $this->configureDefaults();
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
}
