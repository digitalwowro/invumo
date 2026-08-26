<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use RuntimeException;

final class PostgreSqlTestRestore
{
    /** @param array<string, mixed> $connection */
    public function restore(array $connection, string $backupPath): void
    {
        $database = $this->required($connection, 'database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('Backup restore verification requires an isolated test database.');
        }

        $common = [
            '/usr/bin/psql',
            '--no-psqlrc',
            '--set=ON_ERROR_STOP=1',
            '--host='.$this->required($connection, 'host'),
            '--port='.(string) ($connection['port'] ?? 5432),
            '--username='.$this->required($connection, 'username'),
            '--dbname='.$database,
        ];
        $environment = getenv();
        $environment['PGPASSWORD'] = (string) ($connection['password'] ?? '');
        $environment['PGSSLMODE'] = (string) ($connection['sslmode'] ?? 'prefer');

        try {
            $this->run([
                ...$common,
                '--command=DROP SCHEMA public CASCADE; CREATE SCHEMA public AUTHORIZATION CURRENT_USER;',
            ], $environment);
            $this->run([...$common, '--file='.$backupPath], $environment);
        } finally {
            unset($environment['PGPASSWORD']);
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function run(array $command, array $environment): void
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('The isolated PostgreSQL restore process could not start.');
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException('The isolated PostgreSQL restore verification failed.');
        }
    }

    /** @param array<string, mixed> $connection */
    private function required(array $connection, string $key): string
    {
        $value = $connection[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("The test PostgreSQL {$key} is unavailable.");
        }

        return $value;
    }
}
