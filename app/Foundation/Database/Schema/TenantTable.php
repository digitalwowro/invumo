<?php

namespace App\Foundation\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TenantTable
{
    /** @var list<string> */
    private const RUNTIME_PRIVILEGES = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];

    public static function addIdentity(Blueprint $table): void
    {
        $table->uuid('id')->primary();
        $table->foreignUuid('company_id')
            ->constrained('companies')
            ->cascadeOnDelete();
        $table->unique(
            ['company_id', 'id'],
            self::name($table->getTable(), 'company_id_id_unique'),
        );
    }

    public static function money(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->decimal($column, 30, 8);
    }

    public static function quantity(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->decimal($column, 20, 6);
    }

    public static function percentage(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->decimal($column, 12, 6);
    }

    public static function currencyPrecision(
        Blueprint $table,
        string $column = 'currency_precision',
    ): ColumnDefinition {
        return $table->smallInteger($column);
    }

    public static function sameCompanyForeign(
        Blueprint $table,
        string $column,
        string $parentTable,
        string $constraintName,
        bool $cascadeOnDelete = false,
    ): void {
        self::assertIdentifier($column);
        self::assertIdentifier($parentTable);
        self::assertIdentifier($constraintName);

        $foreign = $table->foreign(
            ['company_id', $column],
            $constraintName,
        )->references(['company_id', 'id'])->on($parentTable);

        $cascadeOnDelete
            ? $foreign->cascadeOnDelete()
            : $foreign->restrictOnDelete();

        $table->index(
            ['company_id', $column],
            self::name($table->getTable(), "company_{$column}_index"),
        );
    }

    /**
     * Apply the fail-closed tenant policy and least runtime privileges as one
     * reviewed step after the table and its constraints have been created.
     *
     * @param  list<string>  $runtimePrivileges
     */
    public static function protect(
        string $table,
        array $runtimePrivileges = self::RUNTIME_PRIVILEGES,
    ): void {
        self::assertIdentifier($table);

        $privileges = self::validatedPrivileges($runtimePrivileges);
        $policy = self::name($table, 'company_policy');

        DB::statement("REVOKE ALL ON TABLE public.{$table} FROM PUBLIC");
        DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE public.{$table} FORCE ROW LEVEL SECURITY");
        DB::statement(<<<SQL
            CREATE POLICY {$policy}
            ON public.{$table}
            FOR ALL
            USING (company_id = (SELECT public.invumo_current_company_id()))
            WITH CHECK (company_id = (SELECT public.invumo_current_company_id()))
            SQL);
        DB::statement("COMMENT ON TABLE public.{$table} IS 'invumo:tenant-owned'");

        if (DB::table('pg_roles')->where('rolname', 'invumo_runtime')->exists()) {
            DB::statement("REVOKE ALL ON TABLE public.{$table} FROM invumo_runtime");
            DB::statement("GRANT {$privileges} ON TABLE public.{$table} TO invumo_runtime");
        }
    }

    private static function name(string $table, string $suffix): string
    {
        self::assertIdentifier($table);
        self::assertIdentifier($suffix);

        $name = "{$table}_{$suffix}";

        if (strlen($name) > 63) {
            throw new InvalidArgumentException("PostgreSQL identifier [{$name}] exceeds 63 bytes.");
        }

        return $name;
    }

    /** @param list<string> $privileges */
    private static function validatedPrivileges(array $privileges): string
    {
        if ($privileges === []) {
            throw new InvalidArgumentException('A tenant table requires at least one runtime privilege.');
        }

        foreach ($privileges as $privilege) {
            if (! in_array($privilege, self::RUNTIME_PRIVILEGES, true)) {
                throw new InvalidArgumentException("Unsupported runtime privilege [{$privilege}].");
            }
        }

        return implode(', ', array_values(array_unique($privileges)));
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Unsafe PostgreSQL identifier [{$identifier}].");
        }
    }
}
