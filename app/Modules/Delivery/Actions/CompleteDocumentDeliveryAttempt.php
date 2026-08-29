<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\PreparedProviderAttempt;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;

final readonly class CompleteDocumentDeliveryAttempt
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(
        string $deliveryId,
        PreparedProviderAttempt $prepared,
        ProviderDeliveryResult $result,
        int $queueAttempt,
        int $maxAttempts,
    ): bool {
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = Document::query()->whereKey($prepared->documentId)->lockForUpdate()->firstOrFail();
        match ($document->kind) {
            DocumentKind::Quote => Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
            DocumentKind::Invoice => Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
        };
        $delivery = EmailDelivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();
        $attempt = EmailDeliveryAttempt::query()->whereKey($prepared->attemptId)->lockForUpdate()->firstOrFail();
        $attempt->update([
            'state' => $result->state,
            'provider_message_identifier' => $result->providerMessageIdentifier,
            'failure_category' => $result->failureCategory,
            'failure_summary' => $result->failureSummary,
            'completed_at' => now(),
        ]);

        $retryable = $result->state === EmailDeliveryAttemptState::RetryableRejection;
        $willRetry = $retryable && max(1, $queueAttempt) < $maxAttempts;
        $state = match (true) {
            $result->state === EmailDeliveryAttemptState::Accepted => EmailDeliveryState::Accepted,
            $result->state === EmailDeliveryAttemptState::Unknown => EmailDeliveryState::Unknown,
            $willRetry => EmailDeliveryState::Retrying,
            default => EmailDeliveryState::Rejected,
        };
        $delivery->update([
            'dispatch_state' => $state,
            'provider_message_identifier' => $result->providerMessageIdentifier,
            'failure_category' => $result->failureCategory,
            'failure_summary' => $result->failureSummary,
            'accepted_at' => $state === EmailDeliveryState::Accepted ? now() : null,
            'failed_at' => in_array($state, [EmailDeliveryState::Rejected, EmailDeliveryState::Unknown], true) ? now() : null,
        ]);
        $this->completeReminder($delivery, $state, $result->failureCategory);

        if ($state === EmailDeliveryState::Accepted) {
            $this->markQuoteSent($document);
        }

        if (! $willRetry) {
            $this->auditOutcome($delivery, $state, $attempt->id);
        }

        return $willRetry;
    }

    public function rejectBeforeSubmission(
        EmailDelivery $delivery,
        string $auditReference,
        string $failureCategory,
        string $failureSummary,
    ): void {
        $delivery->update([
            'dispatch_state' => EmailDeliveryState::Rejected,
            'failure_category' => $failureCategory,
            'failure_summary' => $failureSummary,
            'failed_at' => now(),
        ]);
        $this->completeReminder($delivery, EmailDeliveryState::Rejected, $failureCategory);
        $this->auditOutcome($delivery, EmailDeliveryState::Rejected, $auditReference);
    }

    public function markInterrupted(
        EmailDelivery $delivery,
        EmailDeliveryAttempt $attempt,
    ): void {
        $attempt->update([
            'state' => EmailDeliveryAttemptState::Unknown,
            'failure_category' => 'interrupted_submission',
            'failure_summary' => 'The provider submission was interrupted and its outcome is unknown.',
            'completed_at' => now(),
        ]);
        $delivery->update([
            'dispatch_state' => EmailDeliveryState::Unknown,
            'failure_category' => 'interrupted_submission',
            'failure_summary' => 'A previous provider submission was interrupted and its outcome is unknown.',
            'failed_at' => now(),
        ]);
        $this->completeReminder(
            $delivery,
            EmailDeliveryState::Unknown,
            'interrupted_submission',
        );
        $this->auditOutcome($delivery, EmailDeliveryState::Unknown, $attempt->id);
    }

    private function completeReminder(
        EmailDelivery $delivery,
        EmailDeliveryState $state,
        ?string $failureCategory = null,
    ): void {
        if ($delivery->event_type !== EmailTemplateEvent::PaymentReminder
            || $state === EmailDeliveryState::Retrying) {
            return;
        }

        $instance = $delivery->reminder_instance_id === null
            ? null
            : ReminderInstance::query()
                ->whereKey($delivery->reminder_instance_id)->lockForUpdate()->first();

        if (! $instance instanceof ReminderInstance) {
            return;
        }

        $suppressed = $failureCategory === 'reminder_no_longer_eligible';
        $instance->update([
            'status' => match (true) {
                $state === EmailDeliveryState::Accepted => ReminderInstanceStatus::Sent,
                $suppressed => ReminderInstanceStatus::Suppressed,
                default => ReminderInstanceStatus::Failed,
            },
            'failure_category' => $state === EmailDeliveryState::Accepted ? null : $failureCategory,
            'failure_summary' => $state === EmailDeliveryState::Accepted
                ? null : ($delivery->failure_summary ?? 'Reminder delivery failed.'),
            'sent_at' => $state === EmailDeliveryState::Accepted ? now() : null,
            'completed_at' => now(),
        ]);
    }

    private function markQuoteSent(Document $document): void
    {
        if ($document->kind !== DocumentKind::Quote) {
            return;
        }

        $quote = Quote::query()->whereKey($document->id)->firstOrFail();

        if ($quote->lifecycle === QuoteLifecycle::Draft) {
            $quote->update(['lifecycle' => QuoteLifecycle::Sent]);
            $document->update([
                'edit_version' => $document->edit_version + 1,
                'content_version' => $document->content_version + 1,
            ]);
        }
    }

    private function auditOutcome(
        EmailDelivery $delivery,
        EmailDeliveryState $state,
        string $auditReference,
    ): void {
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::System,
            action: 'company.document.delivery.completed',
            targetType: $delivery->document_kind === DocumentKind::Quote ? 'Quote' : 'Invoice',
            targetId: (string) $delivery->document_id,
            idempotencyReference: $auditReference,
            after: AuditPayload::fromAllowedFields([
                'delivery_id' => $delivery->id,
                'dispatch_state' => $state->value,
                'attempt_count' => $delivery->attempts()->count(),
            ], ['delivery_id', 'dispatch_state', 'attempt_count']),
        ));
    }
}
