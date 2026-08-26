<?php

namespace Tests\Feature\Foundation\Database;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantBusinessTableContractTest extends TestCase
{
    use DatabaseMigrations;

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
            $identityColumn = in_array($table->table_name, ['quotes', 'invoices'], true)
                ? 'document_id'
                : 'id';
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
                          AND pg_get_constraintdef(oid) = ?
                    ) AS has_company_identity
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = ?
                  AND column_name = 'company_id'
                SQL, [
                $table->table_name,
                $table->table_name,
                "UNIQUE (company_id, {$identityColumn})",
                $table->table_name,
            ]);

            $this->assertSame('uuid', $contract->data_type, $table->table_name);
            $this->assertSame('NO', $contract->is_nullable, $table->table_name);
            $this->assertTrue($contract->has_policy, $table->table_name);
            $this->assertTrue($contract->has_company_identity, $table->table_name);
        }
    }
}
