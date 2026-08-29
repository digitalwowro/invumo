<?php

namespace App\Modules\Delivery\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Delivery\Actions\PrepareInvoiceReminder;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\ReminderInstance;
use Throwable;

final class SendInvoiceReminder extends TenantJob
{
    public function __construct(string $companyId, public readonly string $instanceId)
    {
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'invoice-reminder:'.$instanceId,
            component: 'delivery.invoice_reminder',
        ));
    }

    public function handle(TenantContext $tenancy, PrepareInvoiceReminder $prepare): void
    {
        $tenancy->runAsSystem(
            $this->identity->companyId,
            function () use ($prepare): void {
                $delivery = $prepare->handle($this->instanceId);

                if ($delivery !== null) {
                    SendDocumentDelivery::dispatch($this->identity->companyId, $delivery->id)
                        ->onConnection('database')->onQueue('default');
                }
            },
        );
    }

    public function failed(?Throwable $exception): void
    {
        app(TenantContext::class)->runAsSystem($this->identity->companyId, function (): void {
            $instance = ReminderInstance::query()->whereKey($this->instanceId)->lockForUpdate()->first();

            if ($instance instanceof ReminderInstance && ! $instance->status->isTerminal()) {
                $instance->update([
                    'status' => ReminderInstanceStatus::Failed,
                    'failure_category' => 'reminder_job_failed',
                    'failure_summary' => 'The reminder worker exhausted its retries.',
                    'completed_at' => now(),
                ]);
                JobDispatch::query()
                    ->where('target_id', $instance->id)
                    ->where('status', JobDispatchStatus::Queued)
                    ->update([
                        'status' => JobDispatchStatus::Completed,
                        'claim_token' => null,
                        'claimed_at' => null,
                    ]);
            }
        });
    }
}
