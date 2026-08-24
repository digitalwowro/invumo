<?php

namespace App\Foundation\Diagnostics;

use App\Foundation\Configuration\ProductionConfiguration;
use App\Foundation\Database\Schema\MigrationDatabaseRole;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApplicationHealth
{
    public function __construct(
        private readonly ProductionConfiguration $productionConfiguration,
    ) {}

    public function diagnose(): void
    {
        $this->productionConfiguration->assertSafeWhenProduction();

        $connectionName = (string) (
            config('database.tenant_connection') ?? config('database.default')
        );
        $result = DB::connection($connectionName)->selectOne(<<<'SQL'
            SELECT current_user AS database_role,
                   nullif(current_setting('app.current_company_id', true), '') AS company_id
            SQL);
        if ($result === null || $result->database_role !== MigrationDatabaseRole::RUNTIME) {
            throw new RuntimeException('Runtime database role mismatch.');
        }

        if ($result->company_id !== null) {
            throw new RuntimeException('Health check inherited a tenant context.');
        }
    }
}
