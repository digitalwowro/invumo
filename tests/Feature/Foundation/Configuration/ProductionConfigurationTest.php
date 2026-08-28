<?php

namespace Tests\Feature\Foundation\Configuration;

use App\Foundation\Configuration\ProductionConfiguration;
use App\Foundation\Database\PostgreSqlClientBinaries;
use RuntimeException;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    public function test_safe_production_contract_passes_without_exposing_secret_values(): void
    {
        $this->setSafeProductionConfiguration();

        app(ProductionConfiguration::class)->assertSafe();
        $this->addToAssertionCount(1);

        config()->set('database.connections.pgsql.password', '');

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('A missing production runtime password must fail validation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('database.runtime_password', $exception->getMessage());
            $this->assertStringNotContainsString('test-secret', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_non_production_configuration_is_not_forced_into_production_topology(): void
    {
        config()->set('app.key', null);

        app(ProductionConfiguration::class)->assertSafeWhenProduction();

        $this->addToAssertionCount(1);
    }

    public function test_deployment_command_fails_outside_production_instead_of_silently_skipping(): void
    {
        $this->artisan('invumo:production-configuration')
            ->expectsOutputToContain('app.env')
            ->assertFailed();
    }

    public function test_deployment_command_reports_unsafe_keys_without_secret_values(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('database.connections.pgsql.password', '');

        try {
            $this->artisan('invumo:production-configuration')
                ->expectsOutputToContain('database.runtime_password')
                ->doesntExpectOutputToContain('schema-test-secret')
                ->doesntExpectOutputToContain('mail-test-secret')
                ->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_deployment_command_accepts_the_safe_production_contract(): void
    {
        $this->setSafeProductionConfiguration();

        try {
            $this->artisan('invumo:production-configuration')
                ->expectsOutput('Invumo production configuration is safe.')
                ->assertSuccessful();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_supported_locale_catalogue_can_expand_without_a_database_allowlist(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('localization.supported_locales', ['en', 'ro', 'pt_BR']);

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->addToAssertionCount(1);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_supported_locale_catalogue_rejects_unsafe_or_duplicate_codes(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('localization.supported_locales', ['en', '../ro', 'en']);

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('An unsafe supported-locale catalogue was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'localization.supported_locales',
                $exception->getMessage(),
            );
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_queue_connection_and_visibility_timeout_must_match_the_worker_contract(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set([
            'queue.connections.database.connection' => 'pgsql_schema',
            'queue.connections.database.retry_after' => 90,
        ]);

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('An unsafe production queue topology was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('queue.database_connection', $exception->getMessage());
            $this->assertStringContainsString('queue.retry_after', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_company_assets_require_a_configured_private_non_served_disk(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('invumo.company_assets.disk', 'public');

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('A public production Company-asset disk was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('filesystem.company_assets', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_document_delivery_requires_private_artifacts_and_safe_zeptomail_configuration(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set([
            'invumo.document_artifacts.disk' => 'public',
            'services.zeptomail.endpoint' => 'http://example.test/send',
            'services.zeptomail.token' => '',
            'services.zeptomail.webhook_secret' => 'short',
            'services.zeptomail.connect_timeout' => 30,
            'services.zeptomail.timeout' => 20,
        ]);

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('Unsafe production document-delivery configuration was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('filesystem.document_artifacts', $exception->getMessage());
            $this->assertStringContainsString('zeptomail.endpoint', $exception->getMessage());
            $this->assertStringContainsString('zeptomail.token', $exception->getMessage());
            $this->assertStringContainsString('zeptomail.webhook_secret', $exception->getMessage());
            $this->assertStringContainsString('zeptomail.timeouts', $exception->getMessage());
            $this->assertStringNotContainsString('zeptomail-test-secret', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_zeptomail_token_must_be_the_bare_send_api_key(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('services.zeptomail.token', 'Zoho-enczapikey zeptomail-test-secret');

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('A complete Authorization header was accepted as a Send API key.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('zeptomail.token', $exception->getMessage());
            $this->assertStringNotContainsString('zeptomail-test-secret', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_document_delivery_quotas_cannot_exceed_shared_reputation_caps(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('invumo.document_delivery.platform_recipients_per_hour', 1001);

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('An unsafe shared-provider quota was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('document_delivery.quotas', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_postgresql_client_must_match_the_configured_major_version(): void
    {
        $this->setSafeProductionConfiguration();
        config()->set('database.postgresql_client.major_version', 17);

        $this->app->forgetInstance(ProductionConfiguration::class);
        $this->app->forgetInstance(PostgreSqlClientBinaries::class);

        try {
            app(ProductionConfiguration::class)->assertSafe();
            $this->fail('An incompatible PostgreSQL client was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('database.postgresql_client', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    private function setSafeProductionConfiguration(): void
    {
        $this->app['env'] = 'production';
        config()->set([
            'app.key' => 'base64:test-key',
            'app.debug' => false,
            'app.url' => 'https://app.example.com',
            'app.timezone' => 'UTC',
            'database.default' => 'pgsql',
            'database.tenant_connection' => 'pgsql',
            'database.connections.pgsql.username' => 'invumo_runtime',
            'database.connections.pgsql.password' => 'test-secret',
            'database.connections.pgsql_schema.username' => 'invumo_schema',
            'database.connections.pgsql_schema.password' => 'schema-test-secret',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'pgsql',
            'queue.connections.database.retry_after' => 120,
            'cache.default' => 'database',
            'cache.stores.tenant_jobs.connection' => 'pgsql',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.password' => 'mail-test-secret',
            'mail.from.address' => 'app@example.com',
            'invumo.document_artifacts.disk' => 'document_artifacts_local',
            'services.zeptomail.endpoint' => 'https://api.zeptomail.eu/v1.1/email',
            'services.zeptomail.token' => 'zeptomail-test-secret',
            'services.zeptomail.webhook_secret' => str_repeat('w', 32),
            'services.zeptomail.timeout' => 20,
            'services.zeptomail.connect_timeout' => 5,
            'localization.supported_locales' => ['en', 'ro'],
        ]);
    }
}
