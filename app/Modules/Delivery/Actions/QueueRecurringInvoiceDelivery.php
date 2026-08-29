<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\AutomatedDeliveryFailure;
use App\Modules\Delivery\Data\AutomatedDeliveryResult;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Queries\DocumentDeliveryComposer;
use App\Modules\Delivery\Rules\ValidateResolvedEmailContent;
use App\Modules\Delivery\Support\DocumentDeliveryLimits;
use App\Modules\Delivery\Support\PublicDocumentUrl;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Recurring\Models\RecurringOccurrence;

final readonly class QueueRecurringInvoiceDelivery
{
    public function __construct(
        private LockDocumentDeliveryHistory $deliveryHistory,
        private EnsurePublicDocumentLink $ensureLink,
        private PublicDocumentUrl $publicUrl,
        private DocumentDeliveryComposer $composer,
        private ValidateResolvedEmailContent $validateContent,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        Company $company,
        Document $document,
        RecurringOccurrence $occurrence,
    ): AutomatedDeliveryResult {
        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $deliverySetting = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)->lockForUpdate()->firstOrFail();
        $links = PublicDocumentLink::query()
            ->where('document_id', $document->id)->orderBy('id')->lockForUpdate()->get();

        if (! $deliverySetting->public_access_enabled) {
            return AutomatedDeliveryResult::suppressed(
                AutomatedDeliveryFailure::PublicAccessDisabled,
            );
        }

        $recipients = DocumentDeliveryRecipient::query()
            ->where('document_id', $document->id)->orderBy('display_order')->get();
        if ($recipients->isEmpty()
            || ! $recipients->contains('role', 'TO')
            || $recipients->count() > DocumentDeliveryLimits::recipientsPerMessage()) {
            return AutomatedDeliveryResult::suppressed(
                AutomatedDeliveryFailure::RecipientsUnavailable,
            );
        }

        if ($this->deliveryHistory->hasPending($document->id)) {
            return AutomatedDeliveryResult::suppressed(
                AutomatedDeliveryFailure::RecipientsUnavailable,
            );
        }

        $access = new LockedPublicDocumentAccess(
            $settings,
            $document,
            $deliverySetting,
            $links,
        );
        $link = $this->ensureLink->handle($access, null);
        $url = $this->publicUrl->for(DocumentKind::Invoice, $link);
        $content = $this->composer->automatedInvoice($company, $document, $url);
        $this->validateContent->handle($content);
        $delivery = EmailDelivery::query()->create([
            'document_id' => $document->id,
            'public_document_link_id' => $link->id,
            'document_kind' => DocumentKind::Invoice,
            'event_type' => EmailTemplateEvent::InvoiceSent,
            'delivery_key' => $occurrence->id,
            'document_edit_version' => $document->edit_version,
            'language_code' => $document->document_language,
            'subject' => $content->subject,
            'body' => $content->body,
            'button_label' => $content->buttonLabel,
            'signature' => $content->signature,
            'button_url' => $url,
            'attachment_mode' => $deliverySetting->email_attachment_mode,
            'provider_name' => 'ZEPTOMAIL',
            'dispatch_state' => EmailDeliveryState::Queued,
            'initiated_by_user_id' => null,
            'recurring_automatic' => true,
        ]);

        foreach ($recipients as $position => $recipient) {
            EmailDeliveryRecipient::query()->create([
                'delivery_id' => $delivery->id,
                'role' => $recipient->role,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'display_order' => $position + 1,
            ]);
        }

        $this->audit($delivery, $recipients->count());
        SendDocumentDelivery::dispatch($company->id, $delivery->id)
            ->onConnection('database')->onQueue('default');

        return AutomatedDeliveryResult::queued($delivery);
    }

    private function audit(EmailDelivery $delivery, int $recipientCount): void
    {
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::ScheduledJob,
            actorReference: 'recurring_automation',
            action: 'company.recurring_template.automatic_delivery_queued',
            targetType: 'Invoice',
            targetId: (string) $delivery->document_id,
            idempotencyReference: $delivery->id,
            after: AuditPayload::fromAllowedFields([
                'delivery_id' => $delivery->id,
                'attachment_mode' => $delivery->attachment_mode?->value,
                'recipient_count' => $recipientCount,
            ], ['delivery_id', 'attachment_mode', 'recipient_count']),
        ));
    }
}
