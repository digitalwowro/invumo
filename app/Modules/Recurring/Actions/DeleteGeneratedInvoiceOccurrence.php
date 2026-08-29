<?php

namespace App\Modules\Recurring\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Recurring\Models\RecurringOccurrence;

final readonly class DeleteGeneratedInvoiceOccurrence
{
    public function __construct(private RecordAuditEvent $recordAuditEvent) {}

    public function handle(User $actor, string $invoiceId): void
    {
        $occurrence = RecurringOccurrence::query()
            ->where('invoice_id', $invoiceId)->lockForUpdate()->first();

        if (! $occurrence instanceof RecurringOccurrence) {
            return;
        }

        $dispatch = JobDispatch::query()
            ->whereKey($occurrence->job_dispatch_id)->lockForUpdate()->first();
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.generated_invoice_deleted',
            targetType: 'RecurringTemplate',
            targetId: $occurrence->recurring_template_id,
            before: AuditPayload::fromAllowedFields([
                'invoice_id' => $invoiceId,
                'occurrence_key' => $occurrence->occurrence_key,
            ], ['invoice_id', 'occurrence_key']),
        ));
        $occurrence->delete();
        $dispatch?->delete();
    }
}
