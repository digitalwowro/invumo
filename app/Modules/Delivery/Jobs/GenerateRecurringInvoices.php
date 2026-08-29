<?php

namespace App\Modules\Delivery\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Jobs\TenantJobExecution;
use App\Modules\Recurring\Actions\ExecuteRecurringGeneration;
use App\Modules\Recurring\Actions\FailRecurringGeneration;
use App\Modules\Recurring\Data\RecurringJobResult;
use Throwable;

final class GenerateRecurringInvoices extends TenantJob
{
    public function __construct(string $companyId, public readonly string $dispatchId)
    {
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'recurring-dispatch:'.$dispatchId,
            component: 'recurring.invoice_generation',
        ));
    }

    public function handle(
        ExecuteRecurringGeneration $generate,
        TenantJobExecution $execution,
    ): void {
        $result = $generate->handle(
            $this->identity->companyId,
            $this->dispatchId,
            $this->attempts(),
        );

        if ($result === RecurringJobResult::NoWork) {
            $execution->skip('recurring_work_not_due');
        }

        if ($result === RecurringJobResult::PermanentFailure) {
            $execution->skip('recurring_permanent_failure');
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(FailRecurringGeneration::class)->handle(
            $this->identity->companyId,
            $this->dispatchId,
            $this->attempts(),
            'worker_failed',
        );
    }
}
