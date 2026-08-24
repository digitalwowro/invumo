<?php

namespace Tests\Support\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TenantProbeJob extends TenantJob
{
    /** @var list<string|null> */
    public static array $observations = [];

    public function __construct(
        string $companyId,
        public readonly string $requestedCompanyId,
        public readonly string $mode = 'succeed',
    ) {
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'test-probe:'.$companyId,
            component: 'test.tenant_probe',
        ));
    }

    public function handle(TenantContext $tenantContext, TenantJobExecution $execution): void
    {
        self::$observations[] = $tenantContext->companyId();

        $tenantContext->runAsSystem($this->requestedCompanyId, function (): void {
            $result = DB::connection(config('database.tenant_connection'))
                ->selectOne('SELECT public.invumo_current_company_id() AS company_id');
            self::$observations[] = $result?->company_id;
        });

        self::$observations[] = $tenantContext->companyId();

        if ($this->mode === 'skip') {
            $execution->skip('probe_skipped');
        }

        if ($this->mode === 'fail') {
            throw new RuntimeException('secret value must never enter an operational log');
        }
    }
}
