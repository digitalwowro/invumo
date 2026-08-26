<?php

namespace App\Providers;

use App\Foundation\Database\DestructiveCommandSafety;
use App\Foundation\Database\ProductionSqlDump;
use App\Foundation\Database\SqlDumpProcess;
use App\Foundation\Diagnostics\ApplicationHealth;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\Contracts\VerifiesTenantMembership;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Queries\CompanyMembershipVerifier;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Numbering\LockedDocumentNumberAllocator;
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
        $this->app->bind(AuthorizesCompanyActions::class, CompanyActionAuthorizer::class);
        $this->app->bind(SqlDumpProcess::class, ProductionSqlDump::class);
        $this->app->bind(AllocatesDocumentNumbers::class, LockedDocumentNumberAllocator::class);
        $this->app->singleton(TenantJobExecution::class);
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

        $destructiveCommandSafety = app(DestructiveCommandSafety::class);

        DB::prohibitDestructiveCommands(! $destructiveCommandSafety->permits(
            app()->environment(),
            [
                config('database.connections.pgsql.database'),
                config('database.connections.pgsql_schema.database'),
            ],
        ));

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
