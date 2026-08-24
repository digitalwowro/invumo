<?php

namespace App\Foundation\Configuration;

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use RuntimeException;

final class ProductionConfiguration
{
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
            'session.driver' => config('session.driver') !== 'database',
            'session.encrypt' => config('session.encrypt') !== true,
            'session.secure' => config('session.secure') !== true,
            'session.http_only' => config('session.http_only') !== true,
            'session.same_site' => ! in_array(config('session.same_site'), ['lax', 'strict'], true),
            'queue.default' => config('queue.default') !== 'database',
            'cache.default' => config('cache.default') !== 'database',
            'mail.default' => config('mail.default') !== 'smtp',
            'mail.smtp_password' => ! $this->nonEmpty(config('mail.mailers.smtp.password')),
            'mail.from' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) === false,
            'localization.supported_locales' => config('localization.supported_locales')
                !== ['en', 'ro'],
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
}
