<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Models\Quote;

final readonly class FailDocumentDelivery
{
    public function __construct(
        private TenantContext $tenantContext,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(string $companyId, string $deliveryId, string $auditReference): void
    {
        $this->tenantContext->runAsSystem($companyId, function () use (
            $deliveryId,
            $auditReference,
        ): void {
            $unlocked = EmailDelivery::query()->whereKey($deliveryId)->first();

            if (! $unlocked instanceof EmailDelivery || $unlocked->document_id === null) {
                return;
            }

            CompanySetting::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->whereKey($unlocked->document_id)->lockForUpdate()->firstOrFail();
            match ($document->kind) {
                DocumentKind::Quote => Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
                DocumentKind::Invoice => Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
            };
            $delivery = EmailDelivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();

            if (! in_array(
                $delivery->dispatch_state,
                [EmailDeliveryState::Queued, EmailDeliveryState::Retrying],
                true,
            ) || $delivery->redacted_at !== null) {
                return;
            }

            $pending = EmailDeliveryAttempt::query()
                ->where('delivery_id', $delivery->id)
                ->where('state', 'PENDING')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $ambiguous = $pending->isNotEmpty();
            $auditIdentity = $auditReference;

            foreach ($pending as $attempt) {
                $auditIdentity = $attempt->id;
                $attempt->update([
                    'state' => 'UNKNOWN',
                    'failure_category' => 'ambiguous_transmission',
                    'failure_summary' => 'The provider transmission outcome could not be confirmed.',
                    'completed_at' => now(),
                ]);
            }

            $delivery->update([
                'dispatch_state' => $ambiguous
                    ? EmailDeliveryState::Unknown : EmailDeliveryState::Rejected,
                'failure_category' => $ambiguous
                    ? 'ambiguous_transmission' : 'internal_delivery_failure',
                'failure_summary' => $ambiguous
                    ? 'The provider transmission outcome could not be confirmed.'
                    : 'The delivery worker could not complete this email.',
                'failed_at' => now(),
            ]);
            if ($delivery->event_type === EmailTemplateEvent::PaymentReminder) {
                ReminderInstance::query()
                    ->whereKey($delivery->reminder_instance_id)
                    ->whereIn('status', [ReminderInstanceStatus::Pending, ReminderInstanceStatus::Claimed])
                    ->update([
                        'status' => ReminderInstanceStatus::Failed,
                        'failure_category' => $delivery->failure_category,
                        'failure_summary' => $delivery->failure_summary,
                        'completed_at' => now(),
                    ]);
            }
            $this->audit->handle(new AuditEventData(
                actorType: AuditActorType::System,
                action: 'company.document.delivery.completed',
                targetType: $document->kind === DocumentKind::Quote ? 'Quote' : 'Invoice',
                targetId: $document->id,
                idempotencyReference: $auditIdentity,
                after: AuditPayload::fromAllowedFields([
                    'delivery_id' => $delivery->id,
                    'dispatch_state' => $delivery->dispatch_state->value,
                    'attempt_count' => $delivery->attempts()->count(),
                ], ['delivery_id', 'dispatch_state', 'attempt_count']),
            ));
        });
    }
}
