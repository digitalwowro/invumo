<?php

namespace Tests\Feature\Foundation\Database;

use App\Foundation\Database\Schema\MigrationDatabaseRole;
use RuntimeException;
use Tests\TestCase;

class MigrationDatabaseRoleTest extends TestCase
{
    public function test_expected_runtime_role_is_available(): void
    {
        $this->assertTrue(MigrationDatabaseRole::runtimeIsAvailable());
    }

    public function test_missing_role_is_allowed_only_in_local_or_testing(): void
    {
        $this->assertFalse(MigrationDatabaseRole::isAvailable('invumo_missing_role_probe'));

        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            MigrationDatabaseRole::isAvailable('invumo_missing_role_probe');
            $this->fail('A production-like migration must reject a missing database role.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Required PostgreSQL role [invumo_missing_role_probe] is missing. '
                .'Create the restricted runtime role before running migrations.',
                $exception->getMessage(),
            );
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }
}
