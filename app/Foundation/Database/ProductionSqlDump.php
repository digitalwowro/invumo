<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use RuntimeException;

final class ProductionSqlDump
{
    private const BINARY = '/usr/bin/pg_dump';

    public function assertAvailable(): void
    {
        if (! is_executable(self::BINARY)) {
            throw new RuntimeException('The PostgreSQL backup binary is unavailable.');
        }
    }

    /** @return list<string> */
    public function command(
        string $host,
        string $port,
        string $username,
        string $database,
        string $snapshot,
    ): array {
        return [
            self::BINARY,
            '--host', $host,
            '--port', $port,
            '--username', $username,
            '--dbname', $database,
            '--format=plain',
            '--snapshot='.$snapshot,
            '--no-password',
        ];
    }

    /**
     * @param  resource  $destination
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    public function append($destination, array $command, array $environment): void
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => $destination, 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('The PostgreSQL backup process could not start.');
        }

        fclose($pipes[0]);
        $standardError = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException($this->failureMessage($standardError));
        }
    }

    public function qualifiedTable(object $table): string
    {
        $schema = $table->schema_name ?? null;
        $name = $table->table_name ?? null;

        if (! is_string($schema) || preg_match('/^[a-z_][a-z0-9_]*$/D', $schema) !== 1
            || ! is_string($name) || preg_match('/^[a-z_][a-z0-9_]*$/D', $name) !== 1) {
            throw new RuntimeException('A tenant table name was not safe to export.');
        }

        return $schema.'.'.$name;
    }

    /** @param array<string, int> $expected */
    public function verifyTenantRowCounts(string $path, array $expected): void
    {
        $actual = array_fill_keys(array_keys($expected), 0);
        $source = fopen($path, 'rb');

        if (! is_resource($source)) {
            throw new RuntimeException('The SQL backup could not be reopened for tenant verification.');
        }

        while (($line = fgets($source)) !== false) {
            if (preg_match('/^INSERT INTO ([a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*) /D', $line, $match) === 1
                && array_key_exists($match[1], $actual)) {
                $actual[$match[1]]++;
            }
        }

        fclose($source);

        if ($actual !== $expected) {
            throw new RuntimeException('The SQL backup did not contain every forced-RLS tenant row.');
        }
    }

    private function failureMessage(string|false $standardError): string
    {
        $diagnostic = is_string($standardError) ? strtolower($standardError) : '';

        return match (true) {
            str_contains($diagnostic, 'row-level security') => 'A tenant-aware forced-RLS backup segment failed.',
            str_contains($diagnostic, 'permission denied') => 'The schema role lacks a required backup permission.',
            str_contains($diagnostic, 'connection') => 'PostgreSQL backup connection failed.',
            default => 'PostgreSQL did not produce a usable backup.',
        };
    }
}
