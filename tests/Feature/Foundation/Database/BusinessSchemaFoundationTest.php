<?php

namespace Tests\Feature\Foundation\Database;

use App\Foundation\Database\Schema\TenantTable;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessSchemaFoundationTest extends TestCase
{
    use DatabaseMigrations;

    private const CHILD_TABLE = 'tenant_schema_probe_children';

    private const PARENT_TABLE = 'tenant_schema_probe_parents';

    public function test_postgresql_extensions_functions_and_domain_instants_are_ready(): void
    {
        $extension = DB::connection('pgsql_schema')->selectOne(
            "SELECT extname FROM pg_extension WHERE extname = 'pg_trgm'",
        );

        $this->assertSame('pg_trgm', $extension->extname);
        $this->assertTrue($this->isQuantized('12.34', 2));
        $this->assertFalse($this->isQuantized('12.345', 2));
        $this->assertFalse($this->isQuantized('12', 9));

        $columns = DB::connection('pgsql_schema')->select(<<<'SQL'
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'users'
              AND column_name IN ('email_verified_at', 'created_at', 'updated_at')
            ORDER BY column_name
            SQL);

        $this->assertCount(3, $columns);
        $this->assertSame(
            ['timestamp with time zone'],
            array_values(array_unique(array_column($columns, 'data_type'))),
        );
    }

    public function test_runtime_role_and_existing_domain_schema_follow_least_privilege(): void
    {
        $runtime = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT rolsuper, rolcreatedb, rolcreaterole, rolbypassrls FROM pg_roles
            WHERE rolname = 'invumo_runtime'
            SQL);

        $this->assertFalse($runtime->rolsuper);
        $this->assertFalse($runtime->rolcreatedb);
        $this->assertFalse($runtime->rolcreaterole);
        $this->assertFalse($runtime->rolbypassrls);

        $migrationAccess = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT
                has_table_privilege('invumo_runtime', 'public.migrations', 'SELECT') AS can_select,
                has_table_privilege('invumo_runtime', 'public.migrations', 'INSERT') AS can_insert,
                has_table_privilege('invumo_runtime', 'public.migrations', 'UPDATE') AS can_update,
                has_table_privilege('invumo_runtime', 'public.migrations', 'DELETE') AS can_delete
            SQL);

        $this->assertFalse($migrationAccess->can_select);
        $this->assertFalse($migrationAccess->can_insert);
        $this->assertFalse($migrationAccess->can_update);
        $this->assertFalse($migrationAccess->can_delete);

        $idTypes = DB::connection('pgsql_schema')->select(<<<'SQL'
            SELECT table_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND column_name = 'id'
              AND table_name NOT IN (
                  'migrations', 'sessions', 'cache', 'cache_locks',
                  'jobs', 'job_batches', 'failed_jobs'
            )
            SQL);
        $this->assertNotEmpty($idTypes);
        foreach ($idTypes as $column) {
            $this->assertSame('uuid', $column->data_type, $column->table_name);
        }
    }

    public function test_every_current_tenant_business_table_has_the_complete_contract(): void
    {
        $tables = DB::connection('pgsql_schema')->select(<<<'SQL'
            SELECT c.relname AS table_name,
                   c.relrowsecurity,
                   c.relforcerowsecurity,
                   owner.rolname AS owner_name,
                   obj_description(c.oid) AS comment
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_roles owner ON owner.oid = c.relowner
            JOIN information_schema.columns columns
              ON columns.table_schema = n.nspname
             AND columns.table_name = c.relname
             AND columns.column_name = 'company_id'
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND c.relname NOT IN ('company_memberships', 'company_invitations')
            ORDER BY c.relname
            SQL);

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $this->assertTrue($table->relrowsecurity, $table->table_name);
            $this->assertTrue($table->relforcerowsecurity, $table->table_name);
            $this->assertNotSame('invumo_runtime', $table->owner_name, $table->table_name);
            $this->assertSame('invumo:tenant-owned', $table->comment, $table->table_name);

            $contract = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
                SELECT
                    columns.data_type,
                    columns.is_nullable,
                    EXISTS (
                        SELECT 1 FROM pg_policies
                        WHERE schemaname = 'public'
                          AND tablename = ?
                          AND qual LIKE '%invumo_current_company_id%'
                    ) AS has_policy,
                    EXISTS (
                        SELECT 1 FROM pg_constraint
                        WHERE conrelid = ('public.' || ?)::regclass
                          AND contype = 'u'
                          AND pg_get_constraintdef(oid) = 'UNIQUE (company_id, id)'
                    ) AS has_company_identity
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = ?
                  AND column_name = 'company_id'
                SQL, [$table->table_name, $table->table_name, $table->table_name]);

            $this->assertSame('uuid', $contract->data_type, $table->table_name);
            $this->assertSame('NO', $contract->is_nullable, $table->table_name);
            $this->assertTrue($contract->has_policy, $table->table_name);
            $this->assertTrue($contract->has_company_identity, $table->table_name);
        }
    }

    public function test_tenant_table_contract_enforces_types_rls_and_same_company_links(): void
    {
        $this->createProbeTables();

        try {
            $companyA = $this->company('Alpha SRL');
            $companyB = $this->company('Beta SRL');
            $parentId = (string) Str::uuid7();

            app(TenantContext::class)->runAsSystem($companyA->id, function () use ($parentId): void {
                DB::connection('pgsql')->table(self::PARENT_TABLE)->insert([
                    'id' => $parentId,
                    'company_id' => app(TenantContext::class)->companyId(),
                    'amount' => '123.45',
                    'quantity' => '2.500000',
                    'percentage' => '19.000000',
                    'currency_precision' => 2,
                ]);

                DB::connection('pgsql')->table(self::CHILD_TABLE)->insert([
                    'id' => (string) Str::uuid7(),
                    'company_id' => app(TenantContext::class)->companyId(),
                    'parent_id' => $parentId,
                ]);

                $this->assertSame(1, DB::connection('pgsql')->table(self::PARENT_TABLE)->count());
            });

            $this->assertSame(0, DB::connection('pgsql')->table(self::PARENT_TABLE)->count());
            $this->assertNull(
                DB::connection('pgsql')->selectOne(
                    'SELECT public.invumo_current_company_id() AS company_id',
                )->company_id,
            );

            $this->assertCrossCompanyReferenceFails($companyB, $parentId);
            $this->assertProbeSchemaContract();
        } finally {
            $this->dropProbeTables();
        }
    }

    private function createProbeTables(): void
    {
        Schema::connection('pgsql_schema')->create(self::PARENT_TABLE, function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            TenantTable::money($table, 'amount');
            TenantTable::quantity($table, 'quantity');
            TenantTable::percentage($table, 'percentage');
            TenantTable::currencyPrecision($table);
        });
        DB::connection('pgsql_schema')->statement(<<<'SQL'
            ALTER TABLE tenant_schema_probe_parents
            ADD CONSTRAINT tenant_schema_probe_precision_check
                CHECK (currency_precision BETWEEN 0 AND 8),
            ADD CONSTRAINT tenant_schema_probe_amount_quantized_check
                CHECK (invumo_amount_is_quantized(amount, currency_precision))
            SQL);
        TenantTable::protect(self::PARENT_TABLE);

        Schema::connection('pgsql_schema')->create(self::CHILD_TABLE, function (Blueprint $table): void {
            TenantTable::addIdentity($table);
            $table->uuid('parent_id');
            TenantTable::sameCompanyForeign(
                $table,
                'parent_id',
                self::PARENT_TABLE,
                'tenant_schema_probe_parent_foreign',
            );
        });
        TenantTable::protect(self::CHILD_TABLE);
    }

    private function assertCrossCompanyReferenceFails(Company $company, string $parentId): void
    {
        try {
            app(TenantContext::class)->runAsSystem($company->id, function () use ($parentId): void {
                DB::connection('pgsql')->table(self::CHILD_TABLE)->insert([
                    'id' => (string) Str::uuid7(),
                    'company_id' => app(TenantContext::class)->companyId(),
                    'parent_id' => $parentId,
                ]);
            });

            $this->fail('A child must not reference a parent from another Company.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertProbeSchemaContract(): void
    {
        $columns = DB::connection('pgsql_schema')->select(<<<'SQL'
            SELECT column_name, data_type, numeric_precision, numeric_scale
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'tenant_schema_probe_parents'
            SQL);
        $byName = collect($columns)->keyBy('column_name');

        $this->assertSame(['numeric', 30, 8], $this->numericShape($byName->get('amount')));
        $this->assertSame(['numeric', 20, 6], $this->numericShape($byName->get('quantity')));
        $this->assertSame(['numeric', 12, 6], $this->numericShape($byName->get('percentage')));
        $this->assertSame('smallint', $byName->get('currency_precision')->data_type);

        $relation = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity, obj_description(oid) AS comment
            FROM pg_class
            WHERE oid = 'public.tenant_schema_probe_parents'::regclass
            SQL);
        $this->assertTrue($relation->relrowsecurity);
        $this->assertTrue($relation->relforcerowsecurity);
        $this->assertSame('invumo:tenant-owned', $relation->comment);
    }

    /** @return array{string, int, int} */
    private function numericShape(object $column): array
    {
        return [$column->data_type, $column->numeric_precision, $column->numeric_scale];
    }

    private function isQuantized(string $amount, int $precision): bool
    {
        return DB::connection('pgsql_schema')->selectOne(
            'SELECT public.invumo_amount_is_quantized(?::numeric, ?::smallint) AS fits',
            [$amount, $precision],
        )->fits;
    }

    private function company(string $name): Company
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return app(CreateCompany::class)->handle($account, $user, $name);
    }

    private function dropProbeTables(): void
    {
        Schema::connection('pgsql_schema')->dropIfExists(self::CHILD_TABLE);
        Schema::connection('pgsql_schema')->dropIfExists(self::PARENT_TABLE);
    }
}
