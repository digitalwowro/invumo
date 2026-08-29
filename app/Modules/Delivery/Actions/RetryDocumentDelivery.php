<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Queries\ProviderSubmissionEligibility;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Recurring\Queries\RecurringAutomaticDeliveryEligibility;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Str;
use LogicException;

final readonly class RetryDocumentDelivery
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private RecurringAutomaticDeliveryEligibility $recurringEligibility,
        private ProviderSubmissionEligibility $submissionEligibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        string $deliveryId,
        bool $confirmed,
    ): void {
        $this->tenantContext->runForMember($actor, $company->id, function () use (
            $company, $actor, $documentId, $deliveryId, $confirmed,
        ): void {
            if (! $confirmed) {
                throw DocumentDeliveryException::retryConfirmationRequired();
            }

            CompanySetting::query()->lockForUpdate()->firstOrFail();
            $delivery = EmailDelivery::query()
                ->whereKey($deliveryId)->where('document_id', $documentId)->firstOrFail();
            $this->authorizer->authorize($actor, $company, $delivery->document_kind->manageAbility());
            $recurringSource = $this->recurringEligibility->lockForDelivery($delivery);
            $document = Document::query()->whereKey($delivery->document_id)->lockForUpdate()->firstOrFail();
            $documentState = match ($delivery->document_kind) {
                DocumentKind::Quote => Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
                DocumentKind::Invoice => Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
            };
            $transactions = $delivery->document_kind === DocumentKind::Invoice
                ? InvoiceTransaction::query()
                    ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get()
                : collect();
            $deliverySetting = DocumentDeliverySetting::query()
                ->where('document_id', $document->id)->lockForUpdate()->firstOrFail();
            $publicLink = PublicDocumentLink::query()
                ->whereKey($delivery->public_document_link_id)
                ->where('document_id', $document->id)
                ->lockForUpdate()
                ->first();
            $deliveries = EmailDelivery::query()
                ->where('document_id', $document->id)
                ->where(function ($query) use ($deliveryId): void {
                    $query->whereKey($deliveryId)->orWhereIn('dispatch_state', [
                        EmailDeliveryState::Queued,
                        EmailDeliveryState::Retrying,
                    ]);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $delivery = $deliveries->firstWhere('id', $deliveryId);

            if (! $delivery instanceof EmailDelivery) {
                throw new LogicException('The authorized delivery was absent from its locked retry set.');
            }

            EmailDeliveryAttempt::query()
                ->where('delivery_id', $delivery->id)->orderBy('id')->lockForUpdate()->get();
            $eligibilityFailure = $this->submissionEligibility->failure(
                $delivery,
                $document,
                $documentState instanceof Invoice ? $documentState : null,
                $transactions,
                $recurringSource,
            );

            if (! $delivery->dispatch_state->canRetryManually()
                || $delivery->event_type === EmailTemplateEvent::PaymentReminder
                || $delivery->document_edit_version !== $document->edit_version
                || $deliveries->contains(fn (EmailDelivery $candidate): bool => $candidate->id !== $delivery->id
                    && in_array($candidate->dispatch_state, [
                        EmailDeliveryState::Queued,
                        EmailDeliveryState::Retrying,
                    ], true))
                || $delivery->redacted_at !== null
                || $eligibilityFailure !== null
                || ! $deliverySetting->public_access_enabled
                || ! $publicLink instanceof PublicDocumentLink
                || $publicLink->revoked_at !== null
                || ! $publicLink->expires_at->isFuture()) {
                throw DocumentDeliveryException::retryUnavailable();
            }

            $delivery->update([
                'dispatch_state' => EmailDeliveryState::Queued,
                'provider_message_identifier' => null,
                'failure_category' => null,
                'failure_summary' => null,
                'failed_at' => null,
            ]);
            $this->audit->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.document.delivery.retry_queued',
                targetType: $delivery->document_kind->value === 'QUOTE' ? 'Quote' : 'Invoice',
                targetId: (string) $delivery->document_id,
                after: AuditPayload::fromAllowedFields([
                    'delivery_id' => $delivery->id,
                    'attempt_count' => $delivery->attempts()->count(),
                ], ['delivery_id', 'attempt_count']),
            ));
            SendDocumentDelivery::dispatch(
                $company->id,
                $delivery->id,
                (string) Str::uuid7(),
            )->onConnection('database')->onQueue('default');
        });
    }
}
