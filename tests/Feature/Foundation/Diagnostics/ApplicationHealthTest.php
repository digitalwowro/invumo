<?php

namespace Tests\Feature\Foundation\Diagnostics;

use App\Foundation\Diagnostics\ApplicationHealth;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;
use Tests\TestCase;

class ApplicationHealthTest extends TestCase
{
    use DatabaseMigrations;

    public function test_health_endpoint_checks_the_restricted_database_boundary(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'up']);
    }

    public function test_health_diagnosis_rejects_an_unexpected_database_role(): void
    {
        $original = config('database.tenant_connection');
        config()->set('database.tenant_connection', 'pgsql_schema');

        try {
            app(ApplicationHealth::class)->diagnose();
            $this->fail('Health must reject an unexpected runtime database role.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Runtime database role mismatch.', $exception->getMessage());
        } finally {
            config()->set('database.tenant_connection', $original);
        }
    }

    public function test_health_reports_down_for_unsafe_production_configuration(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.url', 'http://unsafe.example.test');

        try {
            $this->getJson('/up')
                ->assertInternalServerError()
                ->assertExactJson(['status' => 'down']);

            $this->get('/up')
                ->assertInternalServerError()
                ->assertDontSee('app.url');
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
