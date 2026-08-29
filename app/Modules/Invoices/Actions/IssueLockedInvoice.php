<?php

namespace App\Modules\Invoices\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\InvoiceReminderSchedule;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Data\InvoiceIssueFailure;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Rules\InvoiceIssuability;

final readonly class IssueLockedInvoice
{
    public function __construct(
        private InvoiceIssuability $issuability,
        private InvoiceReminderSchedule $reminders,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Document $document, User $actor, int $editVersion): Document
    {
        return $this->issue(
            $document,
            $editVersion,
            AuditActorType::User,
            $actor->id,
        );
    }

    public function handleScheduled(
        Document $document,
        int $editVersion,
        string $idempotencyReference,
    ): Document {
        return $this->issue(
            $document,
            $editVersion,
            AuditActorType::ScheduledJob,
            idempotencyReference: $idempotencyReference,
        );
    }

    private function issue(
        Document $document,
        int $editVersion,
        AuditActorType $actorType,
        ?string $actorUserId = null,
        ?string $idempotencyReference = null,
    ): Document {
        $invoice = Invoice::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

        if ($invoice->lifecycle === InvoiceLifecycle::Issued) {
            return $document;
        }

        if ($invoice->lifecycle !== InvoiceLifecycle::Draft) {
            throw InvoiceLifecycleException::unavailable();
        }

        if ($document->edit_version !== $editVersion) {
            throw InvoiceLifecycleException::stale();
        }

        $lines = DocumentLine::query()
            ->where('document_id', $document->id)->orderBy('id')->lockForUpdate()->get();
        $this->issuability->assert($document, $invoice, $lines);
        $invoice->update(['lifecycle' => InvoiceLifecycle::Issued]);
        $document->update([
            'edit_version' => $document->edit_version + 1,
            'content_version' => $document->content_version + 1,
        ]);
        $this->reminders->materialize(
            $document,
            $invoice,
            CompanySetting::query()->firstOrFail(),
        );
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: $actorType,
            actorUserId: $actorUserId,
            actorReference: $actorType === AuditActorType::ScheduledJob
                ? 'recurring_automation' : null,
            action: 'company.invoice.issued',
            targetType: 'Invoice',
            targetId: $document->id,
            idempotencyReference: $idempotencyReference,
            before: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Draft->value,
            ], ['lifecycle']),
            after: AuditPayload::fromAllowedFields([
                'lifecycle' => InvoiceLifecycle::Issued->value,
                'edit_version' => $document->edit_version,
            ], ['lifecycle', 'edit_version']),
        ));

        return $document->refresh();
    }

    public function forDelivery(
        Document $document,
        User $actor,
        int $editVersion,
    ): ?InvoiceIssueFailure {
        try {
            $this->handle($document, $actor, $editVersion);

            return null;
        } catch (InvoiceLifecycleException $exception) {
            $failure = InvoiceIssueFailure::tryFrom($exception->reason());

            if ($failure === null) {
                throw $exception;
            }

            return $failure;
        }
    }
}
