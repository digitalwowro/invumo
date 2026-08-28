<?php

namespace App\Foundation\Configuration;

use App\Foundation\Database\PostgreSqlClientBinaries;
use App\Foundation\Database\Schema\MigrationDatabaseRole;
use App\Foundation\Localization\SupportedLocales;
use RuntimeException;

final class ProductionConfiguration
{
    private const QUEUE_WORKER_TIMEOUT_SECONDS = 90;

    public function __construct(
        private readonly PostgreSqlClientBinaries $postgresqlClientBinaries,
    ) {}

    public function assertSafe(): void
    {
        $tenantConnection = config('database.tenant_connection') ?? config('database.default');
        $violations = array_keys(array_filter([
            'app.env' => ! app()->isProduction(),
            'app.key' => ! $this->nonEmpty(config('app.key')),
            'app.debug' => config('app.debug') !== false,
            'app.url' => ! str_starts_with((string) config('app.url'), 'https://'),
            'app.timezone' => config('app.timezone') !== 'UTC',
            'database.default' => config('database.default') !== 'pgsql',
            'database.tenant_connection' => $tenantConnection !== 'pgsql',
            'database.runtime_role' => config('database.connections.pgsql.username')
                !== MigrationDatabaseRole::RUNTIME,
            'database.runtime_password' => ! $this->nonEmpty(
                config('database.connections.pgsql.password'),
            ),
            'database.schema_role' => config('database.connections.pgsql_schema.username')
                !== 'invumo_schema',
            'database.schema_password' => ! $this->nonEmpty(
                config('database.connections.pgsql_schema.password'),
            ),
            'database.role_separation' => config('database.connections.pgsql.username')
                === config('database.connections.pgsql_schema.username'),
            'database.postgresql_client' => ! $this->postgresqlClientBinaries
                ->configurationIsValid(),
            'session.driver' => config('session.driver') !== 'database',
            'session.encrypt' => config('session.encrypt') !== true,
            'session.secure' => config('session.secure') !== true,
            'session.http_only' => config('session.http_only') !== true,
            'session.same_site' => ! in_array(config('session.same_site'), ['lax', 'strict'], true),
            'queue.default' => config('queue.default') !== 'database',
            'queue.database_connection' => config('queue.connections.database.connection')
                !== $tenantConnection,
            'queue.retry_after' => (int) config('queue.connections.database.retry_after')
                <= self::QUEUE_WORKER_TIMEOUT_SECONDS,
            'cache.default' => config('cache.default') !== 'database',
            'cache.tenant_job_connection' => config('cache.stores.tenant_jobs.connection')
                !== $tenantConnection,
            'filesystem.company_assets' => ! $this->companyAssetDiskIsPrivate(),
            'filesystem.document_artifacts' => ! $this->privateDiskIsSafe(
                config('invumo.document_artifacts.disk'),
            ),
            'mail.default' => config('mail.default') !== 'smtp',
            'mail.smtp_password' => ! $this->nonEmpty(config('mail.mailers.smtp.password')),
            'mail.from' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) === false,
            'zeptomail.endpoint' => config('services.zeptomail.endpoint') !== 'https://api.zeptomail.eu/v1.1/email',
            'zeptomail.token' => ! $this->bareCredential(config('services.zeptomail.token')),
            'zeptomail.webhook_secret' => ! $this->webhookSecretIsSafe(),
            'zeptomail.timeouts' => (int) config('services.zeptomail.connect_timeout') < 1
                || (int) config('services.zeptomail.timeout') < (int) config('services.zeptomail.connect_timeout')
                || (int) config('services.zeptomail.timeout') > 60,
            'document_delivery.quotas' => ! $this->documentDeliveryQuotasAreSafe(),
            'localization.supported_locales' => ! SupportedLocales::configurationIsValid(),
        ]));

        if ($violations !== []) {
            throw new RuntimeException(
                'Unsafe production configuration: '.implode(', ', $violations).'.',
            );
        }
    }

    public function assertSafeWhenProduction(): void
    {
        if (app()->isProduction()) {
            $this->assertSafe();
        }
    }

    private function nonEmpty(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && ! str_contains($value, '__INVUMO_');
    }

    private function companyAssetDiskIsPrivate(): bool
    {
        return $this->privateDiskIsSafe(config('invumo.company_assets.disk'));
    }

    private function documentDeliveryQuotasAreSafe(): bool
    {
        $quota = config('invumo.document_delivery');

        if (! is_array($quota)) {
            return false;
        }

        $maximums = [
            'max_recipients_per_message' => 10,
            'company_recipients_per_hour' => 100,
            'company_recipients_per_day' => 500,
            'account_recipients_per_hour' => 250,
            'account_recipients_per_day' => 1000,
            'platform_recipients_per_hour' => 1000,
            'platform_recipients_per_day' => 5000,
        ];

        foreach ($maximums as $key => $maximum) {
            $value = $quota[$key] ?? null;

            if (! is_int($value) || $value < 1 || $value > $maximum) {
                return false;
            }
        }

        return $quota['company_recipients_per_hour'] <= $quota['company_recipients_per_day']
            && $quota['account_recipients_per_hour'] <= $quota['account_recipients_per_day']
            && $quota['platform_recipients_per_hour'] <= $quota['platform_recipients_per_day']
            && $quota['max_recipients_per_message'] <= $quota['company_recipients_per_hour']
            && $quota['company_recipients_per_hour'] <= $quota['account_recipients_per_hour']
            && $quota['account_recipients_per_hour'] <= $quota['platform_recipients_per_hour']
            && $quota['company_recipients_per_day'] <= $quota['account_recipients_per_day']
            && $quota['account_recipients_per_day'] <= $quota['platform_recipients_per_day'];
    }

    private function bareCredential(mixed $value): bool
    {
        return $this->nonEmpty($value)
            && trim($value) === $value
            && preg_match('/\s/u', $value) !== 1
            && ! str_starts_with(strtolower($value), 'zoho-enczapikey');
    }

    private function webhookSecretIsSafe(): bool
    {
        $secret = config('services.zeptomail.webhook_secret');

        return $this->nonEmpty($secret)
            && is_string($secret)
            && strlen($secret) >= 32
            && strlen($secret) <= 512
            && trim($secret) === $secret;
    }

    private function privateDiskIsSafe(mixed $diskName): bool
    {

        if (! is_string($diskName) || $diskName === '' || $diskName === 'public') {
            return false;
        }

        $disk = config("filesystems.disks.{$diskName}");

        return is_array($disk)
            && ($disk['visibility'] ?? 'private') !== 'public'
            && ($disk['serve'] ?? false) !== true;
    }
}
