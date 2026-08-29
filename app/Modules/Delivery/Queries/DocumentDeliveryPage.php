<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\EmailTemplateFieldLimits;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\EmailProviderEvent;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Support\DocumentDeliveryLimits;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;

final readonly class DocumentDeliveryPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private DocumentDeliveryComposer $composer,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, string $documentId, DocumentKind $kind): array
    {
        $canSend = $this->abilities->allows($actor, $company, $kind->manageAbility());
        $events = $kind === DocumentKind::Quote
            ? [EmailTemplateEvent::QuoteSent]
            : [EmailTemplateEvent::InvoiceSent, EmailTemplateEvent::PaymentReceived];
        $deliveries = EmailDelivery::query()
            ->with(['recipients', 'providerEvents'])
            ->withCount('attempts')
            ->where('document_id', $documentId)
            ->whereIn('event_type', $events)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $timezone = CompanySetting::query()->value('timezone') ?? 'UTC';
        $accessEnabled = (bool) DocumentDeliverySetting::query()
            ->where('document_id', $documentId)->value('public_access_enabled');
        $activeLinkIds = PublicDocumentLink::query()
            ->where('document_id', $documentId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->pluck('id');
        $currentEditVersion = Document::query()->whereKey($documentId)->value('edit_version');

        return [
            'locale' => app()->getLocale(),
            'timezone' => $timezone,
            'composer' => $canSend ? $this->composer->for($company, $actor, $documentId, $kind) : null,
            'history' => $deliveries->map(fn (EmailDelivery $delivery): array => [
                'id' => $delivery->id,
                'eventType' => $delivery->event_type->value,
                'state' => $delivery->dispatch_state->value,
                'subject' => $delivery->subject,
                'attachmentMode' => $delivery->attachment_mode?->value,
                'createdAt' => $delivery->created_at->toIso8601String(),
                'acceptedAt' => $delivery->accepted_at?->toIso8601String(),
                'failureSummary' => $this->failureSummary($delivery->failure_category),
                'attemptCount' => $delivery->attempts_count,
                'providerEvents' => $delivery->providerEvents->map(fn (EmailProviderEvent $event): array => [
                    'type' => $event->event_type->value,
                    'occurredAt' => $event->occurred_at->toIso8601String(),
                ])->values()->all(),
                'recipients' => $delivery->recipients->map(
                    fn (EmailDeliveryRecipient $recipient): array => [
                        'role' => $recipient->role->value,
                        'name' => $recipient->name,
                        'email' => $recipient->email,
                    ],
                )->values()->all(),
                'retryUrl' => $canSend
                    && $accessEnabled
                    && $delivery->public_document_link_id !== null
                    && $activeLinkIds->contains($delivery->public_document_link_id)
                    && $delivery->document_edit_version === $currentEditVersion
                    && $delivery->dispatch_state->canRetryManually()
                    ? route(
                        $kind === DocumentKind::Quote
                            ? 'quotes.deliveries.retry' : 'invoices.deliveries.retry',
                        [$company, $documentId, $delivery],
                        false,
                    ) : null,
            ])->values()->all(),
            'limits' => [
                'subject' => EmailTemplateFieldLimits::SUBJECT,
                'body' => EmailTemplateFieldLimits::BODY,
                'buttonLabel' => EmailTemplateFieldLimits::BUTTON_LABEL,
                'signature' => EmailTemplateFieldLimits::SIGNATURE,
                'recipients' => DocumentDeliveryLimits::recipientsPerMessage(),
            ],
        ];
    }

    private function failureSummary(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $key = "document_delivery.failures.{$category}";
        $translated = __($key);

        return $translated === $key
            ? __('document_delivery.failures.generic')
            : $translated;
    }
}
