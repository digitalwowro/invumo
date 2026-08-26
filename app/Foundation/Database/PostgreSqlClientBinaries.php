<?php

declare(strict_types=1);

namespace App\Foundation\Database;

use RuntimeException;

final readonly class PostgreSqlClientBinaries
{
    public function __construct(
        private string $directory,
        private int $majorVersion,
    ) {}

    public function pgDump(): string
    {
        return $this->binary('pg_dump');
    }

    public function psql(): string
    {
        return $this->binary('psql');
    }

    public function assertCompatible(): void
    {
        $this->assertBinaryVersion('pg_dump');
        $this->assertBinaryVersion('psql');
    }

    public function configurationIsValid(): bool
    {
        try {
            $this->assertCompatible();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function assertBinaryVersion(string $name): void
    {
        $binary = $this->binary($name);

        if (! is_executable($binary)) {
            throw new RuntimeException('A configured PostgreSQL client binary is unavailable.');
        }

        $pipes = [];
        $process = proc_open(
            [$binary, '--version'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('A configured PostgreSQL client binary could not start.');
        }

        fclose($pipes[0]);
        $version = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0
            || ! is_string($version)
            || preg_match(
                '/^'.preg_quote($name, '/').' \(PostgreSQL\) '
                    .preg_quote((string) $this->majorVersion, '/').'\./D',
                trim($version),
            ) !== 1) {
            throw new RuntimeException('A configured PostgreSQL client binary has an incompatible major version.');
        }
    }

    private function binary(string $name): string
    {
        if ($this->majorVersion < 1
            || ! str_starts_with($this->directory, '/')
            || str_contains($this->directory, "\0")) {
            throw new RuntimeException('The PostgreSQL client configuration is invalid.');
        }

        return rtrim($this->directory, '/').'/'.$name;
    }
}
