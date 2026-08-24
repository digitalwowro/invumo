<?php

namespace App\Foundation\Jobs;

use App\Foundation\Diagnostics\OperationalLogger;
use Closure;
use LogicException;
use Throwable;

final class TenantJobExecution
{
    private ?JobIdentity $activeIdentity = null;

    private bool $skipped = false;

    private ?string $skipCode = null;

    public function __construct(private readonly OperationalLogger $logger) {}

    /** @param Closure(): void $callback */
    public function run(
        JobIdentity $identity,
        string $correlationId,
        int $attempt,
        int $maxAttempts,
        Closure $callback,
    ): void {
        if ($this->activeIdentity !== null) {
            throw new LogicException('Tenant job execution scopes cannot be nested.');
        }

        $startedAt = hrtime(true);
        $this->activeIdentity = $identity;
        $this->log($identity, $correlationId, $attempt, $maxAttempts, 'started', $startedAt);

        try {
            $callback();
            $this->log(
                $identity,
                $correlationId,
                $attempt,
                $maxAttempts,
                $this->skipped ? 'skipped' : 'succeeded',
                $startedAt,
                $this->skipCode,
            );
        } catch (Throwable $exception) {
            $this->log(
                $identity,
                $correlationId,
                $attempt,
                $maxAttempts,
                $attempt >= $maxAttempts ? 'failed' : 'retrying',
                $startedAt,
                'job_exception',
            );

            throw $exception;
        } finally {
            $this->activeIdentity = null;
            $this->skipped = false;
            $this->skipCode = null;
        }
    }

    public function skip(string $errorCode): void
    {
        if ($this->activeIdentity === null) {
            throw new LogicException('A job can only be skipped inside its execution scope.');
        }

        $this->skipped = true;
        $this->skipCode = $errorCode;
    }

    public function ensureCompany(string $companyId): void
    {
        if ($this->activeIdentity !== null && $this->activeIdentity->companyId !== $companyId) {
            throw new LogicException('A tenant job cannot enter another Company context.');
        }
    }

    private function log(
        JobIdentity $identity,
        string $correlationId,
        int $attempt,
        int $maxAttempts,
        string $outcome,
        int $startedAt,
        ?string $errorCode = null,
    ): void {
        $context = [
            'component' => $identity->component,
            'correlation_id' => $correlationId,
            'attempt' => $attempt,
            'count' => $maxAttempts,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'outcome' => $outcome,
        ];

        if ($errorCode !== null) {
            $context['error_code'] = $errorCode;
        }

        $this->logger->info('queue.tenant_job', $context);
    }
}
