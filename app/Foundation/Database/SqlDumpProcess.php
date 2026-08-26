<?php

declare(strict_types=1);

namespace App\Foundation\Database;

interface SqlDumpProcess
{
    public function assertAvailable(): void;

    /** @return list<string> */
    public function command(
        string $host,
        string $port,
        string $username,
        string $database,
        string $snapshot,
    ): array;

    /**
     * @param  resource  $destination
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    public function append($destination, array $command, array $environment): void;

    public function qualifiedTable(object $table): string;

    /** @param array<string, int> $expected */
    public function verifyTenantRowCounts(string $path, array $expected): void;
}
