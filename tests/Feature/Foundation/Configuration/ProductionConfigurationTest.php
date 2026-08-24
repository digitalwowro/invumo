<?php

namespace Tests\Feature\Foundation\Configuration;

use App\Foundation\Configuration\ProductionConfiguration;
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
            'localization.supported_locales' => ['en', 'ro'],
        ]);
    }
}
