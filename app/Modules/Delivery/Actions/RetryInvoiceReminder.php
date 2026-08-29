<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryInvoiceReminder
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $invoiceId,
        string $instanceId,
    ): void {
        $this->tenantContext->runForMember($actor, $company->id, fn (): mixed => DB::connection(
            config('database.tenant_connection'),
        )->transaction(function () use ($company, $actor, $invoiceId, $instanceId): bool {
            $this->authorizer->authorize($actor, $company, CompanyAbility::ViewOperations);
            CompanySetting::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()
                ->whereKey($invoiceId)->where('kind', DocumentKind::Invoice)
                ->lockForUpdate()->firstOrFail();
            Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $instances = ReminderInstance::query()
                ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();
            $instance = $instances->firstWhere('id', $instanceId);

            if (! $instance instanceof ReminderInstance) {
                abort(404);
            }

            if ($instance->status !== ReminderInstanceStatus::Failed) {
                throw ValidationException::withMessages([
                    'reminder' => __('invoices_ui.reminders.errors.retry_unavailable'),
                ]);
            }

            $failureCategory = $instance->failure_category;

            $dispatch = JobDispatch::query()
                ->where('target_id', $instance->id)->lockForUpdate()->firstOrFail();
            $instance->update([
                'status' => ReminderInstanceStatus::Pending,
                'failure_category' => null,
                'failure_summary' => null,
                'completed_at' => null,
            ]);
            $dispatch->update([
                'status' => JobDispatchStatus::Pending,
                'due_at' => now(),
                'claim_token' => null,
                'claimed_at' => null,
            ]);
            $this->audit->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.invoice.reminder.retry_queued',
                targetType: 'Invoice',
                targetId: $invoiceId,
                after: AuditPayload::fromAllowedFields([
                    'reminder_instance_id' => $instance->id,
                    'failure_category' => $failureCategory,
                ], ['reminder_instance_id', 'failure_category']),
            ));

            return true;
        }, 3));
    }
}
