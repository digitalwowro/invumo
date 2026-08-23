<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();

        $this->assertSafeTestEnvironment($application);

        return $application;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function assertSafeTestEnvironment(Application $application): void
    {
        if (! $application->environment('testing')) {
            throw new RuntimeException(
                'Refusing to run Laravel tests outside the testing environment. Clear cached production configuration first.',
            );
        }

        foreach (['pgsql', 'pgsql_schema'] as $connection) {
            $database = $application['config']->string(
                "database.connections.{$connection}.database",
            );

            if (! str_ends_with($database, '_test')) {
                throw new RuntimeException(
                    "Refusing to run Laravel tests because [{$connection}] targets a non-test database.",
                );
            }
        }
    }
}
