<?php

namespace App\Providers;

use App\Foundation\Database\DestructiveCommandSafety;
use App\Foundation\Database\PostgreSqlClientBinaries;
use App\Foundation\Database\ProductionSqlDump;
use App\Foundation\Database\SqlDumpProcess;
use App\Foundation\Diagnostics\ApplicationHealth;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\Contracts\VerifiesTenantMembership;
use App\Foundation\Tenancy\TenantContext;
use App\Integrations\Dompdf\DompdfDocumentPdfRenderer;
use App\Integrations\ZeptoMail\ZeptoMailSendApi;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Queries\CompanyMembershipVerifier;
use App\Modules\Delivery\Contracts\GeneratesPublicDocumentTokens;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Support\CryptographicPublicDocumentToken;
use App\Modules\Delivery\Support\PublicDocumentRateLimitKey;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Numbering\LockedDocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PostgreSqlClientBinaries::class,
            fn (): PostgreSqlClientBinaries => new PostgreSqlClientBinaries(
                (string) config('database.postgresql_client.binary_directory'),
                (int) config('database.postgresql_client.major_version'),
            ),
        );
        $this->app->bind(
            VerifiesTenantMembership::class,
            CompanyMembershipVerifier::class,
        );
        $this->app->bind(AuthorizesCompanyActions::class, CompanyActionAuthorizer::class);
        $this->app->bind(SqlDumpProcess::class, ProductionSqlDump::class);
        $this->app->bind(AllocatesDocumentNumbers::class, LockedDocumentNumberAllocator::class);
        $this->app->bind(RendersDocumentPdf::class, DompdfDocumentPdfRenderer::class);
        $this->app->bind(SendsProviderEmail::class, ZeptoMailSendApi::class);
        $this->app->bind(
            GeneratesPublicDocumentTokens::class,
            CryptographicPublicDocumentToken::class,
        );
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
        $this->configurePublicDocumentRateLimits();
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

    private function configurePublicDocumentRateLimits(): void
    {
        RateLimiter::for('public-document-view', fn (Request $request): array => [
            Limit::perMinute(60)->by('source:'.PublicDocumentRateLimitKey::source($request)),
            Limit::perMinute(120)->by('token:'.PublicDocumentRateLimitKey::token($request)),
        ]);
        RateLimiter::for('public-document-pdf', fn (Request $request): array => [
            Limit::perMinute(10)->by('source:'.PublicDocumentRateLimitKey::source($request)),
            Limit::perMinute(10)->by('token:'.PublicDocumentRateLimitKey::token($request)),
        ]);
        RateLimiter::for('public-document-decision', fn (Request $request): array => [
            Limit::perMinute(10)->by('source:'.PublicDocumentRateLimitKey::source($request)),
            Limit::perMinute(5)->by('token:'.PublicDocumentRateLimitKey::token($request)),
        ]);
    }
}
