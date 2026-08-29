<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\JobDispatchStatus;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Data\PublicDocumentLinkRevocationKind;
use App\Modules\Delivery\Data\ReminderInstanceStatus;
use App\Modules\Delivery\Data\ReminderRelation;
use App\Modules\Delivery\Exceptions\RetryableReminderException;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Delivery\Models\ReminderInstance;
use App\Modules\Delivery\Queries\ReminderDeliveryComposer;
use App\Modules\Delivery\Support\PublicDocumentUrl;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Data\InvoiceLedger;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class PrepareInvoiceReminder
{
    public function __construct(
        private CreatePublicDocumentLinkGeneration $createLink,
        private PublicDocumentUrl $publicUrl,
        private ReminderDeliveryComposer $composer,
        private LockDocumentDeliveryHistory $deliveryHistory,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(string $instanceId): ?EmailDelivery
    {
        $unlocked = ReminderInstance::query()->whereKey($instanceId)->first();

        if (! $unlocked instanceof ReminderInstance) {
            return null;
        }

        $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
        $document = Document::query()->whereKey($unlocked->invoice_id)->lockForUpdate()->firstOrFail();
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $transactions = InvoiceTransaction::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $instances = ReminderInstance::query()
            ->where('invoice_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $instance = $instances->firstWhere('id', $instanceId);

        if (! $instance instanceof ReminderInstance) {
            abort(404);
        }

        $dispatch = JobDispatch::query()
            ->where('target_id', $instance->id)->lockForUpdate()->firstOrFail();

        if ($instance->status->isTerminal()) {
            $this->completeDispatch($dispatch);

            return null;
        }

        if ($instance->scheduled_at->isFuture()) {
            $dispatch->update([
                'status' => JobDispatchStatus::Pending,
                'due_at' => $instance->scheduled_at,
                'claim_token' => null,
                'claimed_at' => null,
            ]);

            return null;
        }

        $ledger = InvoiceLedger::fromTransactions($transactions);

        if ($invoice->lifecycle !== InvoiceLifecycle::Issued
            || $ledger->outstanding($document->total)->isZero()) {
            $this->finish($instance, ReminderInstanceStatus::Suppressed, 'nothing_outstanding');
            $this->completeDispatch($dispatch);

            return null;
        }

        if ($this->isStaleOrSuperseded($instance, $invoice, $instances)) {
            $this->completeDispatch($dispatch);

            return null;
        }

        if ($this->deliveryHistory->hasPending($document->id)) {
            throw new RetryableReminderException('Another document delivery is still pending.');
        }

        $deliverySetting = DocumentDeliverySetting::query()
            ->where('document_id', $document->id)->lockForUpdate()->firstOrFail();
        $links = PublicDocumentLink::query()
            ->where('document_id', $document->id)->orderBy('id')->lockForUpdate()->get();

        if (! $deliverySetting->public_access_enabled) {
            $this->finish($instance, ReminderInstanceStatus::Failed, 'public_access_disabled');
            $this->completeDispatch($dispatch);

            return null;
        }

        $recipients = DocumentDeliveryRecipient::query()
            ->where('document_id', $document->id)->orderBy('display_order')->get();

        if ($recipients->isEmpty() || ! $recipients->contains('role', 'TO')) {
            $this->finish($instance, ReminderInstanceStatus::Failed, 'recipients_unavailable');
            $this->completeDispatch($dispatch);

            return null;
        }

        $company = Company::query()->whereKey($document->company_id)->firstOrFail();
        $access = new LockedPublicDocumentAccess($settings, $document, $deliverySetting, $links);
        $link = $this->link($access);
        $url = $this->publicUrl->for(DocumentKind::Invoice, $link);
        $content = $this->composer->for($company, $document, $ledger, $url);
        $delivery = EmailDelivery::query()->create([
            'document_id' => $document->id,
            'public_document_link_id' => $link->id,
            'reminder_instance_id' => $instance->id,
            'document_kind' => DocumentKind::Invoice,
            'event_type' => EmailTemplateEvent::PaymentReminder,
            'delivery_key' => (string) Str::uuid7(),
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

        $instance->update([
            'status' => ReminderInstanceStatus::Claimed,
            'attempts_count' => $instance->attempts_count + 1,
            'claimed_at' => now(),
        ]);
        $this->completeDispatch($dispatch);
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::System,
            action: 'company.invoice.reminder.queued',
            targetType: 'Invoice',
            targetId: $document->id,
            idempotencyReference: $delivery->id,
            after: AuditPayload::fromAllowedFields([
                'reminder_instance_id' => $instance->id,
                'delivery_id' => $delivery->id,
                'relation' => $instance->relation->value,
                'day_offset' => $instance->day_offset,
                'recipient_count' => $recipients->count(),
            ], ['reminder_instance_id', 'delivery_id', 'relation', 'day_offset', 'recipient_count']),
        ));

        return $delivery;
    }

    /** @param Collection<int, ReminderInstance> $instances */
    private function isStaleOrSuperseded(
        ReminderInstance $instance,
        Invoice $invoice,
        Collection $instances,
    ): bool {
        $today = Date::now($instance->scheduled_timezone)->toImmutable()->startOfDay();

        if ($instance->relation === ReminderRelation::BeforeDue
            && $invoice->due_date !== null
            && $today->greaterThan($invoice->due_date)) {
            $this->finish($instance, ReminderInstanceStatus::Skipped, 'stale_before_due');

            return true;
        }

        if ($instance->relation !== ReminderRelation::AfterDue) {
            return false;
        }

        $newest = $instances
            ->where('relation', ReminderRelation::AfterDue)
            ->whereIn('status', [ReminderInstanceStatus::Pending, ReminderInstanceStatus::Claimed])
            ->filter(fn (ReminderInstance $candidate): bool => ! $candidate->scheduled_at->isFuture())
            ->sortByDesc(fn (ReminderInstance $candidate): string => $candidate->scheduled_at->format('U.u').'|'.$candidate->id,
            )->first();

        if ($newest instanceof ReminderInstance && $newest->id !== $instance->id) {
            $this->finish($instance, ReminderInstanceStatus::Superseded, 'newer_after_due');

            return true;
        }

        return false;
    }

    private function link(LockedPublicDocumentAccess $access): PublicDocumentLink
    {
        $current = $access->current();

        if ($current instanceof PublicDocumentLink && $current->expires_at->isFuture()) {
            return $current;
        }

        if ($current instanceof PublicDocumentLink) {
            $current->update([
                'revoked_at' => now(),
                'revocation_kind' => PublicDocumentLinkRevocationKind::Regenerated,
            ]);
        }

        $link = $this->createLink->handle($access, null);
        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::System,
            action: 'company.document.public_link.created',
            targetType: 'Invoice',
            targetId: $access->document->id,
            after: AuditPayload::fromAllowedFields([
                'access_enabled' => true,
                'generation' => $link->generation,
                'expires_at' => $link->expires_at->toIso8601String(),
            ], ['access_enabled', 'generation', 'expires_at']),
        ));

        return $link;
    }

    private function finish(
        ReminderInstance $instance,
        ReminderInstanceStatus $status,
        string $reason,
    ): void {
        $instance->update([
            'status' => $status,
            'failure_category' => $reason,
            'failure_summary' => $reason,
            'completed_at' => now(),
        ]);
    }

    private function completeDispatch(JobDispatch $dispatch): void
    {
        $dispatch->update([
            'status' => JobDispatchStatus::Completed,
            'claim_token' => null,
            'claimed_at' => null,
        ]);
    }
}
