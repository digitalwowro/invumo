<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class PostgreSqlDatabaseBackup
{
    public function __construct(
        private SqlDumpProcess $sqlDump,
        private PrivateSqlBackupFiles $files,
    ) {}

    /** @return array{path: string, bytes: int, sha256: string} */
    public function handle(
        string $connectionName,
        string $directory,
        string $filenamePrefix,
    ): array {
        $connection = $this->connectionConfiguration($connectionName);
        $database = $this->requiredString($connection, 'database');
        $directory = $this->files->prepareDirectory($directory);
        $this->assertPrefix($filenamePrefix);
        $this->sqlDump->assertAvailable();

        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Ymd\THis\Z');
        $finalPath = "{$directory}/{$filenamePrefix}-{$timestamp}-pre-migration.sql";
        $temporaryPath = $finalPath.'.partial-'.bin2hex(random_bytes(6));
        $environment = $this->processEnvironment($connection);
        $destination = $this->files->open($temporaryPath);

        $schema = DB::connection($connectionName);

        try {
            $schema->beginTransaction();
            $schema->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $snapshot = $schema->selectOne('SELECT pg_export_snapshot() AS id')?->id;

            if (! is_string($snapshot) || $snapshot === '') {
                throw new RuntimeException('PostgreSQL could not export a consistent backup snapshot.');
            }

            $tenantTables = $this->tenantTables($schema);
            $companyIds = $schema->select('SELECT id::text AS id FROM public.companies ORDER BY id');
            $common = $this->sqlDump->command(
                $this->requiredString($connection, 'host'),
                (string) ($connection['port'] ?? 5432),
                $this->requiredString($connection, 'username'),
                $database,
                $snapshot,
            );
            $expectedTenantRows = [];

            foreach ($tenantTables as $table) {
                $expectedTenantRows[$this->sqlDump->qualifiedTable($table)] = 0;
            }

            $this->sqlDump->append(
                $destination,
                [...$common, '--schema-only', '--section=pre-data'],
                $environment,
            );
            $globalData = [...$common, '--data-only'];

            foreach ($tenantTables as $table) {
                $globalData[] = '--exclude-table-data='.$this->sqlDump->qualifiedTable($table);
            }

            $this->sqlDump->append($destination, $globalData, $environment);

            foreach ($companyIds as $company) {
                $this->appendCompanyData(
                    $schema,
                    $company,
                    $tenantTables,
                    $common,
                    $environment,
                    $expectedTenantRows,
                    $destination,
                );
            }

            $this->sqlDump->append(
                $destination,
                [...$common, '--schema-only', '--section=post-data'],
                $environment,
            );

            if (! fflush($destination)) {
                throw new RuntimeException('The SQL backup could not be flushed for verification.');
            }

            $this->sqlDump->verifyTenantRowCounts($temporaryPath, $expectedTenantRows);
            $schema->rollBack();
            fclose($destination);
        } catch (Throwable $exception) {
            if ($schema->transactionLevel() > 0) {
                $schema->rollBack();
            }

            fclose($destination);
            $this->files->removePartial($temporaryPath, $directory);

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Production database backup failed before completion.');
        } finally {
            unset($environment['PGPASSWORD']);
        }

        return $this->files->finalize($temporaryPath, $finalPath, $directory);
    }

    /**
     * @param  list<object>  $tenantTables
     * @param  list<string>  $common
     * @param  array<string, string>  $environment
     * @param  array<string, int>  $expectedTenantRows
     * @param  resource  $destination
     */
    private function appendCompanyData(
        ConnectionInterface $schema,
        object $company,
        array $tenantTables,
        array $common,
        array $environment,
        array &$expectedTenantRows,
        $destination,
    ): void {
        $companyId = get_object_vars($company)['id'] ?? null;

        if (! is_string($companyId) || $companyId === '') {
            throw new RuntimeException('A Company identifier could not be read for backup.');
        }

        $tenantEnvironment = $environment;
        $tenantEnvironment['PGOPTIONS'] = '-c app.current_company_id='.$companyId;
        $schema->selectOne(
            "SELECT set_config('app.current_company_id', ?, true)",
            [$companyId],
        );

        foreach ($tenantTables as $table) {
            $qualifiedTable = $this->sqlDump->qualifiedTable($table);
            $rowCount = $schema->selectOne(
                "SELECT count(*)::bigint AS count FROM {$qualifiedTable}",
            )?->count;

            if (! is_int($rowCount)
                && (! is_string($rowCount) || ! ctype_digit($rowCount))) {
                throw new RuntimeException('A tenant table row count could not be verified.');
            }

            $expectedTenantRows[$qualifiedTable] += (int) $rowCount;
            $this->sqlDump->append($destination, [
                ...$common,
                '--data-only',
                '--enable-row-security',
                '--column-inserts',
                '--table='.$qualifiedTable,
            ], $tenantEnvironment);
        }
    }

    /** @return array<string, mixed> */
    private function connectionConfiguration(string $connectionName): array
    {
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            throw new RuntimeException('The PostgreSQL schema connection is unavailable.');
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, string>
     */
    private function processEnvironment(array $connection): array
    {
        $environment = getenv();
        $environment['PGPASSWORD'] = (string) ($connection['password'] ?? '');
        $environment['PGSSLMODE'] = (string) ($connection['sslmode'] ?? 'prefer');

        return $environment;
    }

    /** @return list<object> */
    private function tenantTables(ConnectionInterface $schema): array
    {
        return array_values($schema->select(<<<'SQL'
            SELECT namespace.nspname AS schema_name, relation.relname AS table_name
            FROM pg_class AS relation
            JOIN pg_namespace AS namespace ON namespace.oid = relation.relnamespace
            WHERE relation.relkind IN ('r', 'p')
                AND relation.relforcerowsecurity
                AND EXISTS (
                    SELECT 1
                    FROM pg_attribute AS attribute
                    WHERE attribute.attrelid = relation.oid
                        AND attribute.attname = 'company_id'
                        AND NOT attribute.attisdropped
                )
            ORDER BY namespace.nspname, relation.relname
            SQL));
    }

    /** @param array<string, mixed> $connection */
    private function requiredString(array $connection, string $key): string
    {
        $value = $connection[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("The PostgreSQL {$key} is unavailable.");
        }

        return $value;
    }

    private function assertPrefix(string $prefix): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $prefix) !== 1) {
            throw new RuntimeException('The SQL backup filename prefix is invalid.');
        }
    }
}
