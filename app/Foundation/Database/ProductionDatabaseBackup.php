<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ProductionDatabaseBackup
{
    private const BACKUP_DIRECTORY = '/home/invumo/backups';

    public function __construct(private readonly ProductionSqlDump $sqlDump) {}

    /** @return array{path: string, bytes: int, sha256: string} */
    public function handle(): array
    {
        $connection = $this->connectionConfiguration();
        $database = $this->requiredString($connection, 'database');
        $this->assertEnvironment($database);
        $this->prepareDirectory();

        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Ymd\THis\Z');
        $finalPath = self::BACKUP_DIRECTORY."/invumo-{$timestamp}-pre-migration.sql";
        $temporaryPath = $finalPath.'.partial-'.bin2hex(random_bytes(6));
        $environment = $this->processEnvironment($connection);
        $destination = fopen($temporaryPath, 'xb');

        if (! is_resource($destination)) {
            throw new RuntimeException('The private SQL backup file could not be created.');
        }

        $schema = DB::connection('pgsql_schema');

        try {
            $schema->beginTransaction();
            $schema->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $snapshot = $schema->selectOne('SELECT pg_export_snapshot() AS id')?->id;

            if (! is_string($snapshot) || $snapshot === '') {
                throw new RuntimeException('PostgreSQL could not export a consistent backup snapshot.');
            }

            $tenantTables = $this->tenantTables();
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
            fflush($destination);
            $this->sqlDump->verifyTenantRowCounts($temporaryPath, $expectedTenantRows);
            $schema->rollBack();
            fclose($destination);
        } catch (Throwable $exception) {
            if ($schema->transactionLevel() > 0) {
                $schema->rollBack();
            }

            fclose($destination);
            $this->removePartialBackup($temporaryPath);

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Production database backup failed before completion.');
        } finally {
            unset($environment['PGPASSWORD']);
        }

        return $this->finalize($temporaryPath, $finalPath);
    }

    /**
     * @param  list<object>  $tenantTables
     * @param  list<string>  $common
     * @param  array<string, string>  $environment
     * @param  array<string, int>  $expectedTenantRows
     * @param  resource  $destination
     */
    private function appendCompanyData(
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
        $schema = DB::connection('pgsql_schema');
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
    private function connectionConfiguration(): array
    {
        $connection = config('database.connections.pgsql_schema');

        if (! is_array($connection)) {
            throw new RuntimeException('The PostgreSQL schema connection is unavailable.');
        }

        return $connection;
    }

    private function assertEnvironment(string $database): void
    {
        if ((string) config('app.env') !== 'production') {
            throw new RuntimeException(
                'Production database backups require the production application environment.',
            );
        }

        if (str_ends_with($database, '_test')) {
            throw new RuntimeException('The production backup command refuses a disposable test database.');
        }

        $this->sqlDump->assertAvailable();
    }

    private function prepareDirectory(): void
    {
        umask(0077);

        if (! is_dir(self::BACKUP_DIRECTORY)
            && ! mkdir(self::BACKUP_DIRECTORY, 0700, true)
            && ! is_dir(self::BACKUP_DIRECTORY)) {
            throw new RuntimeException('The private backup directory could not be created.');
        }

        if (! chmod(self::BACKUP_DIRECTORY, 0700)) {
            throw new RuntimeException('The private backup directory permissions could not be enforced.');
        }
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
    private function tenantTables(): array
    {
        return array_values(DB::connection('pgsql_schema')->select(<<<'SQL'
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

    /** @return array{path: string, bytes: int, sha256: string} */
    private function finalize(string $temporaryPath, string $finalPath): array
    {
        $size = filesize($temporaryPath);
        $header = file_get_contents($temporaryPath, false, null, 0, 256);

        if (! is_int($size) || $size <= 0
            || ! is_string($header)
            || ! str_contains($header, 'PostgreSQL database dump')) {
            $this->removePartialBackup($temporaryPath);
            throw new RuntimeException('The generated SQL backup failed integrity checks.');
        }

        if (! chmod($temporaryPath, 0600) || ! rename($temporaryPath, $finalPath)) {
            $this->removePartialBackup($temporaryPath);
            throw new RuntimeException('The SQL backup could not be finalized securely.');
        }

        $checksum = hash_file('sha256', $finalPath);

        if (! is_string($checksum)) {
            throw new RuntimeException('The SQL backup checksum could not be calculated.');
        }

        return ['path' => $finalPath, 'bytes' => $size, 'sha256' => $checksum];
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

    private function removePartialBackup(string $path): void
    {
        if (is_file($path)
            && dirname($path) === self::BACKUP_DIRECTORY
            && str_contains(basename($path), '.partial-')) {
            unlink($path);
        }
    }
}
