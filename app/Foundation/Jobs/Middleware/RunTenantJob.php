<?php

namespace App\Foundation\Jobs\Middleware;

use App\Foundation\Jobs\TenantJob;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use Closure;

final readonly class RunTenantJob
{
    public function __construct(
        private TenantJobExecution $execution,
        private TenantContext $tenantContext,
    ) {}

    /** @param Closure(TenantJob): void $next */
    public function handle(TenantJob $job, Closure $next): void
    {
        $this->tenantContext->assertClear();

        try {
            $this->execution->run(
                identity: $job->identity,
                correlationId: $job->correlationId(),
                attempt: $job->attempts(),
                maxAttempts: $job->tries,
                callback: fn () => $next($job),
            );
        } finally {
            $this->tenantContext->assertClear();
        }
    }
}
