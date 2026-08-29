<?php

namespace App\Modules\Delivery\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\ResolveOutwardBrandTheme;
use App\Modules\Delivery\Actions\CompleteDocumentDeliveryAttempt;
use App\Modules\Delivery\Actions\FailDocumentDelivery;
use App\Modules\Delivery\Actions\PrepareDocumentDeliveryArtifact;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailRecipientData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\PreparedProviderAttempt;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Exceptions\RetryableProviderRejection;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Rules\DocumentDeliverySenderEligibility;
use App\Modules\Delivery\Rules\PaymentReceivedDeliveryEligibility;
use App\Modules\Delivery\Rules\ReminderDeliveryEligibility;
use App\Modules\Delivery\Support\DocumentDeliveryLimits;
use App\Modules\Delivery\Support\DocumentDeliveryQuota;
use App\Modules\Delivery\Support\DocumentEmailHtml;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Throwable;

final class SendDocumentDelivery extends TenantJob
{
    public readonly string $dispatchCycle;

    public function __construct(string $companyId, public readonly string $deliveryId, ?string $dispatchCycle = null)
    {
        $this->dispatchCycle = $dispatchCycle ?? $deliveryId;
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'document-delivery:'.$deliveryId.':'.$this->dispatchCycle,
            component: 'delivery.document_email',
        ));
    }

    public function handle(
        TenantContext $tenantContext,
        TenantJobExecution $execution,
        SendsProviderEmail $provider,
        FilesystemManager $filesystems,
        ResolveOutwardBrandTheme $brandTheme,
        DocumentEmailHtml $html,
        PrepareDocumentDeliveryArtifact $artifact,
        CompleteDocumentDeliveryAttempt $complete,
        DocumentDeliveryQuota $quota,
        ReminderDeliveryEligibility $reminderEligibility,
        PaymentReceivedDeliveryEligibility $paymentReceivedEligibility,
        DocumentDeliverySenderEligibility $senderEligibility,
    ): void {
        if (! $artifact->handle(
            $this->identity->companyId,
            $this->deliveryId,
            $this->dispatchCycle,
            $execution,
        )) {
            return;
        }

        $prepared = $tenantContext->runAsSystem(
            $this->identity->companyId,
            fn (): ?PreparedProviderAttempt => $this->prepare(
                $execution, $filesystems, $brandTheme, $html, $complete, $quota,
                $reminderEligibility,
                $paymentReceivedEligibility,
                $senderEligibility,
            ),
        );

        if (! $prepared instanceof PreparedProviderAttempt) {
            return;
        }

        $result = $provider->send($prepared->delivery);
        $retry = $tenantContext->runAsSystem(
            $this->identity->companyId,
            fn (): bool => $complete->handle(
                $this->deliveryId,
                $prepared,
                $result,
                $this->attempts(),
                $this->tries,
            ),
        );

        if ($retry && max(1, $this->attempts()) < $this->tries) {
            throw new RetryableProviderRejection;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(FailDocumentDelivery::class)->handle(
            $this->identity->companyId,
            $this->deliveryId,
            $this->dispatchCycle,
        );
    }

    private function prepare(
        TenantJobExecution $execution,
        FilesystemManager $filesystems,
        ResolveOutwardBrandTheme $brandTheme,
        DocumentEmailHtml $html,
        CompleteDocumentDeliveryAttempt $completion,
        DocumentDeliveryQuota $quota,
        ReminderDeliveryEligibility $reminderEligibility,
        PaymentReceivedDeliveryEligibility $paymentReceivedEligibility,
        DocumentDeliverySenderEligibility $senderEligibility,
    ): ?PreparedProviderAttempt {
        $unlocked = EmailDelivery::query()->whereKey($this->deliveryId)->first();

        if (! $unlocked instanceof EmailDelivery || $unlocked->document_id === null) {
            $execution->skip('delivery_unavailable');

            return null;
        }

        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = Document::query()->whereKey($unlocked->document_id)->lockForUpdate()->firstOrFail();
        $invoice = match ($document->kind) {
            DocumentKind::Quote => Quote::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
            DocumentKind::Invoice => Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail(),
        };
        $transactions = $document->kind === DocumentKind::Invoice
            ? InvoiceTransaction::query()
                ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get()
            : collect();
        $deliverySetting = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)->lockForUpdate()->firstOrFail();
        $publicLink = PublicDocumentLink::query()
            ->whereKey($unlocked->public_document_link_id)
            ->where('document_id', $document->id)
            ->lockForUpdate()
            ->first();
        $delivery = EmailDelivery::query()->whereKey($this->deliveryId)->lockForUpdate()->first();

        if (! $delivery instanceof EmailDelivery
            || ! in_array($delivery->dispatch_state, [EmailDeliveryState::Queued, EmailDeliveryState::Retrying], true)
            || $delivery->document_id === null) {
            $execution->skip('delivery_unavailable');

            return null;
        }

        $companyRecord = Company::query()->whereKey($this->identity->companyId)->firstOrFail();
        $account = $companyRecord->owningAccount()->firstOrFail();
        if (! $senderEligibility->allows($companyRecord, $account, $delivery)) {
            $completion->rejectBeforeSubmission(
                $delivery,
                $this->dispatchCycle,
                'sender_access_unavailable',
                'The initiating Company, Account, or User cannot submit provider email.',
            );
            $execution->skip('sender_access_unavailable');

            return null;
        }

        $eligibilityFailure = match ($delivery->event_type) {
            EmailTemplateEvent::PaymentReminder => $reminderEligibility->allows(
                $delivery, $document, $invoice instanceof Invoice ? $invoice : null, $transactions,
            ) ? null : [
                'category' => 'reminder_no_longer_eligible',
                'summary' => 'The Invoice no longer qualified for this reminder.',
            ],
            EmailTemplateEvent::PaymentReceived => $paymentReceivedEligibility->allows(
                $delivery, $invoice instanceof Invoice ? $invoice : null, $transactions,
            ) ? null : [
                'category' => 'payment_received_no_longer_eligible',
                'summary' => 'The referenced Payment no longer qualified for this message.',
            ],
            default => null,
        };

        if ($eligibilityFailure !== null) {
            $completion->rejectBeforeSubmission(
                $delivery,
                $this->dispatchCycle,
                $eligibilityFailure['category'],
                $eligibilityFailure['summary'],
            );
            $execution->skip($eligibilityFailure['category']);

            return null;
        }

        if (! $deliverySetting->public_access_enabled
            || ! $publicLink instanceof PublicDocumentLink
            || $publicLink->revoked_at !== null
            || ! $publicLink->expires_at->isFuture()) {
            $completion->rejectBeforeSubmission(
                $delivery,
                $this->dispatchCycle,
                'public_link_unavailable',
                'The secure public link was unavailable before provider submission.',
            );
            $execution->skip('public_link_unavailable');

            return null;
        }

        $pending = EmailDeliveryAttempt::query()
            ->where('delivery_id', $delivery->id)
            ->where('state', EmailDeliveryAttemptState::Pending)
            ->lockForUpdate()
            ->first();

        if ($pending instanceof EmailDeliveryAttempt) {
            $completion->markInterrupted($delivery, $pending);
            $execution->skip('ambiguous_previous_attempt');

            return null;
        }

        $recipients = EmailDeliveryRecipient::query()
            ->where('delivery_id', $delivery->id)->orderBy('display_order')->get();

        if ($recipients->count() > DocumentDeliveryLimits::recipientsPerMessage()
            || ! $quota->consume(
                $companyRecord->id,
                $companyRecord->owning_account_id,
                $recipients->count(),
            )) {
            $completion->rejectBeforeSubmission(
                $delivery,
                $this->dispatchCycle,
                'sending_quota_exceeded',
                'The shared provider sending quota was exhausted before submission.',
            );
            $execution->skip('sending_quota_exceeded');

            return null;
        }
        $artifact = $delivery->artifact_id === null
            ? null : DocumentArtifact::query()->whereKey($delivery->artifact_id)->firstOrFail();
        $company = DocumentCompanySnapshot::query()
            ->where('document_id', $delivery->document_id)->firstOrFail();
        $theme = $brandTheme->for($company->primary_brand_color);
        $number = EmailDeliveryAttempt::query()->where('delivery_id', $delivery->id)->count() + 1;
        $reference = (string) Str::uuid7();
        $attempt = EmailDeliveryAttempt::query()->create([
            'delivery_id' => $delivery->id,
            'attempt_number' => $number,
            'client_reference' => $reference,
            'state' => EmailDeliveryAttemptState::Pending,
            'submitted_at' => now(),
        ]);
        $recipientData = array_values($recipients->map(fn (EmailDeliveryRecipient $recipient): EmailRecipientData => new EmailRecipientData(
            $recipient->role, $recipient->name, $recipient->email, $recipient->display_order,
        ))->all());
        $attachmentBytes = $artifact === null
            ? null : $filesystems->disk($artifact->storage_disk)->get($artifact->storage_key);
        $text = $delivery->body."\n\n".$delivery->button_label.': '.$delivery->button_url
            .($delivery->signature === null ? '' : "\n\n".$delivery->signature);

        return new PreparedProviderAttempt($attempt->id, $delivery->document_id, new ProviderDelivery(
            clientReference: $reference,
            language: $delivery->language_code,
            recipients: $recipientData,
            subject: (string) $delivery->subject,
            textBody: $text,
            htmlBody: $html->render(
                (string) $delivery->body,
                (string) $delivery->button_label,
                (string) $delivery->button_url,
                $delivery->signature,
                $delivery->language_code,
                $theme->accentColor,
                $theme->onAccentColor,
            ),
            attachmentName: $artifact?->file_name,
            attachmentBytes: $attachmentBytes,
        ));
    }
}
