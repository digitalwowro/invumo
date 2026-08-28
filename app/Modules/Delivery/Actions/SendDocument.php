<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailRecipientData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\EmailTemplateFieldLimits;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Data\SendDocumentData;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Queries\CurrentDocumentRepresentation;
use App\Modules\Delivery\Support\PublicDocumentUrl;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Actions\IssueLockedInvoice;
use App\Modules\Invoices\Data\InvoiceIssueFailure;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\Date;

final readonly class SendDocument
{
    public function __construct(
        private TenantContext $tenantContext,
        private LockPublicDocumentAccess $lockAccess,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private EnsurePublicDocumentLink $ensureLink,
        private PublicDocumentUrl $publicUrl,
        private IssueLockedInvoice $issueInvoice,
        private CurrentDocumentRepresentation $representation,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
        SendDocumentData $data,
    ): EmailDelivery {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): EmailDelivery => $this->persist(
                $company, $actor, $documentId, $kind, $data,
            ),
        );
    }

    private function persist(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
        SendDocumentData $data,
    ): EmailDelivery {
        $access = $this->lockAccess->handle($company, $actor, $documentId, $kind);
        $existing = EmailDelivery::query()->where('delivery_key', $data->deliveryKey)->first();

        if ($existing instanceof EmailDelivery) {
            if ($existing->document_id !== $documentId || $existing->document_kind !== $kind) {
                throw DocumentDeliveryException::deliveryKeyConflict();
            }

            return $existing;
        }

        if ($this->deliveryHistory->hasPending($documentId)) {
            throw DocumentDeliveryException::deliveryPending();
        }

        if ($access->document->edit_version !== $data->editVersion) {
            throw DocumentDeliveryException::stale();
        }

        if ($kind === DocumentKind::Quote) {
            $this->assertQuoteCanSend($access, $data);
        } else {
            $failure = $this->issueInvoice->forDelivery(
                $access->document,
                $actor,
                $data->editVersion,
            );

            if ($failure !== null) {
                throw match ($failure) {
                    InvoiceIssueFailure::Stale => DocumentDeliveryException::stale(),
                    InvoiceIssueFailure::Incomplete => DocumentDeliveryException::invoiceIncomplete(),
                    InvoiceIssueFailure::Unavailable => DocumentDeliveryException::invoiceUnavailable(),
                };
            }
        }

        $link = $this->ensureLink->handle($access, $actor);
        $url = $this->publicUrl->for($kind, $link);
        $outward = $kind === DocumentKind::Quote
            ? $this->representation->forQuote($company, $actor, $documentId)
            : $this->representation->forInvoice($company, $actor, $documentId);
        $subject = $this->resolveUrl($data->subject, $url);
        $body = $this->resolveUrl($data->body, $url);
        $buttonLabel = $this->resolveUrl($data->buttonLabel, $url);
        $signature = $data->signature === null
            ? null : $this->resolveUrl($data->signature, $url);
        $this->assertResolvedContent($subject, $body, $buttonLabel, $signature);
        $delivery = EmailDelivery::query()->create([
            'document_id' => $documentId,
            'public_document_link_id' => $link->id,
            'document_kind' => $kind,
            'event_type' => $kind === DocumentKind::Quote
                ? EmailTemplateEvent::QuoteSent : EmailTemplateEvent::InvoiceSent,
            'delivery_key' => $data->deliveryKey,
            'document_edit_version' => $access->document->edit_version,
            'language_code' => $outward->language,
            'subject' => $subject,
            'body' => $body,
            'button_label' => $buttonLabel,
            'signature' => $signature,
            'button_url' => $url,
            'attachment_mode' => $data->attachmentMode,
            'artifact_id' => null,
            'provider_name' => 'ZEPTOMAIL',
            'dispatch_state' => EmailDeliveryState::Queued,
            'initiated_by_user_id' => $actor->id,
        ]);
        $this->recipients($delivery, $data->recipients);
        $this->audit($actor, $delivery);
        SendDocumentDelivery::dispatch($company->id, $delivery->id)
            ->onConnection('database')->onQueue('default');

        return $delivery;
    }

    private function assertQuoteCanSend(
        LockedPublicDocumentAccess $access,
        SendDocumentData $data,
    ): void {
        $quote = Quote::query()->whereKey($access->document->id)->lockForUpdate()->firstOrFail();
        $lines = DocumentLine::query()
            ->where('document_id', $access->document->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $complete = $lines->isNotEmpty()
            && $lines->every(fn (DocumentLine $line): bool => $line->final_line_total !== null);
        $required = $access->document->customer_id !== null
            && $access->document->rendered_number !== ''
            && $access->document->issue_date !== null
            && $quote->valid_until !== null
            && $access->document->currency_code !== null
            && $access->document->currency_precision !== null
            && $access->document->document_language !== null
            && $access->document->companySnapshot()->exists()
            && $access->document->customerSnapshot()->exists();
        $draftIsCurrent = $quote->lifecycle !== QuoteLifecycle::Draft
            || $quote->valid_until->greaterThanOrEqualTo(
                Date::now($access->settings->timezone ?? 'UTC')->toImmutable()->startOfDay(),
            );

        if (! $complete || ! $required || ! $draftIsCurrent) {
            throw DocumentDeliveryException::quoteIncomplete();
        }

        if (in_array($quote->lifecycle, [QuoteLifecycle::Accepted, QuoteLifecycle::Rejected], true)
            && ! $data->confirmedFinalQuoteState) {
            throw DocumentDeliveryException::finalQuoteConfirmationRequired();
        }
    }

    /** @param list<EmailRecipientData> $recipients */
    private function recipients(EmailDelivery $delivery, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            EmailDeliveryRecipient::query()->create([
                'delivery_id' => $delivery->id,
                'role' => $recipient->role,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'display_order' => $recipient->displayOrder,
            ]);
        }
    }

    private function resolveUrl(string $content, string $url): string
    {
        return str_replace('{{public_url}}', $url, $content);
    }

    private function assertResolvedContent(
        string $subject,
        string $body,
        string $buttonLabel,
        ?string $signature,
    ): void {
        foreach ([
            'subject' => [$subject, EmailTemplateFieldLimits::SUBJECT],
            'body' => [$body, EmailTemplateFieldLimits::BODY],
            'button_label' => [$buttonLabel, EmailTemplateFieldLimits::BUTTON_LABEL],
            'signature' => [$signature, EmailTemplateFieldLimits::SIGNATURE],
        ] as $field => [$value, $limit]) {
            if (is_string($value) && mb_strlen($value) > $limit) {
                throw DocumentDeliveryException::resolvedContentTooLong($field);
            }
        }
    }

    private function audit(User $actor, EmailDelivery $delivery): void
    {
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.document.delivery.queued',
            targetType: $delivery->document_kind === DocumentKind::Quote ? 'Quote' : 'Invoice',
            targetId: $delivery->document_id,
            after: AuditPayload::fromAllowedFields([
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type->value,
                'attachment_mode' => $delivery->attachment_mode?->value,
                'recipient_count' => $delivery->recipients()->count(),
            ], ['delivery_id', 'event_type', 'attachment_mode', 'recipient_count']),
        ));
    }
}
