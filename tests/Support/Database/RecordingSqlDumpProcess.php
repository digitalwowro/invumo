<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use App\Foundation\Database\SqlDumpProcess;
use Closure;
use RuntimeException;

final class RecordingSqlDumpProcess implements SqlDumpProcess
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<string> */
    public array $tenantContexts = [];

    private int $appendCount = 0;

    public function __construct(
        private readonly SqlDumpProcess $inner,
        private readonly ?Closure $afterAppend = null,
        private readonly ?int $expireSnapshotOnAppend = null,
        private readonly bool $forceRowMismatch = false,
    ) {}

    public function assertAvailable(): void
    {
        $this->inner->assertAvailable();
    }

    public function command(
        string $host,
        string $port,
        string $username,
        string $database,
        string $snapshot,
    ): array {
        return $this->inner->command($host, $port, $username, $database, $snapshot);
    }

    public function append($destination, array $command, array $environment): void
    {
        $this->appendCount++;

        if ($this->appendCount === $this->expireSnapshotOnAppend) {
            $command = array_map(
                fn (string $argument): string => str_starts_with($argument, '--snapshot=')
                    ? '--snapshot=expired-test-snapshot'
                    : $argument,
                $command,
            );
        }

        $this->commands[] = $command;
        $tenantContext = $environment['PGOPTIONS'] ?? null;

        if (is_string($tenantContext)) {
            $this->tenantContexts[] = $tenantContext;
        }

        $this->inner->append($destination, $command, $environment);

        if ($this->afterAppend instanceof Closure) {
            ($this->afterAppend)($this->appendCount);
        }
    }

    public function qualifiedTable(object $table): string
    {
        return $this->inner->qualifiedTable($table);
    }

    public function verifyTenantRowCounts(string $path, array $expected): void
    {
        if ($this->forceRowMismatch) {
            throw new RuntimeException('The SQL backup did not contain every forced-RLS tenant row.');
        }

        $this->inner->verifyTenantRowCounts($path, $expected);
    }
}
