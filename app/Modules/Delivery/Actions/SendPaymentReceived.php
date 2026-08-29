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
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Data\SendPaymentReceivedData;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Queries\PaymentReceivedDeliveryComposer;
use App\Modules\Delivery\Rules\ValidateResolvedEmailContent;
use App\Modules\Delivery\Support\DocumentDeliveryLimits;
use App\Modules\Delivery\Support\PublicDocumentUrl;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Data\InvoiceTransactionKind;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Database\Eloquent\Collection;

final readonly class SendPaymentReceived
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private EnsurePublicDocumentLink $ensureLink,
        private PublicDocumentUrl $publicUrl,
        private PaymentReceivedDeliveryComposer $composer,
        private ValidateResolvedEmailContent $validateContent,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $invoiceId,
        string $transactionId,
        SendPaymentReceivedData $data,
    ): EmailDelivery {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): EmailDelivery => $this->send(
                $company,
                $actor,
                $invoiceId,
                $transactionId,
                $data,
            ),
        );
    }

    private function send(
        Company $company,
        User $actor,
        string $invoiceId,
        string $transactionId,
        SendPaymentReceivedData $data,
    ): EmailDelivery {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);

        if (! $data->confirmed) {
            throw DocumentDeliveryException::paymentReceivedConfirmationRequired();
        }

        $settings = CompanySetting::query()->orderBy('id')->lockForUpdate()->firstOrFail();
        $document = Document::query()
            ->whereKey($invoiceId)->where('kind', DocumentKind::Invoice)
            ->lockForUpdate()->firstOrFail();
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $payment = $transactions->firstWhere('id', $transactionId);
        abort_unless($payment instanceof InvoiceTransaction, 404);

        $deliverySetting = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)->lockForUpdate()->firstOrFail();
        $links = PublicDocumentLink::query()
            ->where('document_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $deliveries = EmailDelivery::query()
            ->where('document_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $existing = EmailDelivery::query()
            ->where('delivery_key', $data->deliveryKey)->first();

        if ($existing instanceof EmailDelivery) {
            if ($existing->event_type !== EmailTemplateEvent::PaymentReceived
                || $existing->invoice_transaction_id !== $payment->id) {
                throw DocumentDeliveryException::deliveryKeyConflict();
            }

            return $existing;
        }

        $this->assertAvailable($invoice, $payment, $data, $deliveries);
        $recipients = DocumentDeliveryRecipient::query()
            ->where('document_id', $document->id)
            ->orderBy('display_order')->orderBy('id')->lockForUpdate()->get();

        if ($recipients->isEmpty()
            || ! $recipients->contains('role', 'TO')
            || $recipients->count() > DocumentDeliveryLimits::recipientsPerMessage()) {
            throw DocumentDeliveryException::paymentReceivedRecipientsUnavailable();
        }

        $access = new LockedPublicDocumentAccess($settings, $document, $deliverySetting, $links);
        $link = $this->ensureLink->handle($access, $actor);
        $url = $this->publicUrl->for(DocumentKind::Invoice, $link);
        $content = $this->composer->for(
            $company,
            $document,
            $payment,
            InvoiceLedger::fromTransactions($transactions),
            $url,
        );
        $this->validateContent->handle($content);
        $delivery = EmailDelivery::query()->create([
            'document_id' => $document->id,
            'public_document_link_id' => $link->id,
            'invoice_transaction_id' => $payment->id,
            'invoice_transaction_edit_version' => $payment->edit_version,
            'document_kind' => DocumentKind::Invoice,
            'event_type' => EmailTemplateEvent::PaymentReceived,
            'delivery_key' => $data->deliveryKey,
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
            'initiated_by_user_id' => $actor->id,
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

        $this->audit($actor, $payment, $delivery, $recipients->count());
        SendDocumentDelivery::dispatch($company->id, $delivery->id)
            ->onConnection('database')->onQueue('default');

        return $delivery;
    }

    /** @param Collection<int, EmailDelivery> $deliveries */
    private function assertAvailable(
        Invoice $invoice,
        InvoiceTransaction $payment,
        SendPaymentReceivedData $data,
        Collection $deliveries,
    ): void {
        if ($invoice->lifecycle !== InvoiceLifecycle::Issued
            || $payment->kind !== InvoiceTransactionKind::Payment) {
            throw DocumentDeliveryException::paymentReceivedUnavailable();
        }

        if ($payment->edit_version !== $data->transactionEditVersion) {
            throw DocumentDeliveryException::stale();
        }

        if ($deliveries->contains(fn (EmailDelivery $delivery): bool => in_array(
            $delivery->dispatch_state,
            [EmailDeliveryState::Queued, EmailDeliveryState::Retrying],
            true,
        ))) {
            throw DocumentDeliveryException::deliveryPending();
        }
    }

    private function audit(
        User $actor,
        InvoiceTransaction $payment,
        EmailDelivery $delivery,
        int $recipientCount,
    ): void {
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.payment_received.queued',
            targetType: 'InvoiceTransaction',
            targetId: $payment->id,
            idempotencyReference: $delivery->delivery_key,
            after: AuditPayload::fromAllowedFields([
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type->value,
                'attachment_mode' => $delivery->attachment_mode?->value,
                'recipient_count' => $recipientCount,
            ], ['delivery_id', 'event_type', 'attachment_mode', 'recipient_count']),
        ));
    }
}
