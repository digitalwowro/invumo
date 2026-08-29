<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Documents\DocumentCalendar;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Delivery\Models\DocumentReminderRule;
use App\Modules\Documents\Actions\ApplyDocumentCustomer;
use App\Modules\Documents\Actions\InitializeDocumentDefaults;
use App\Modules\Documents\Actions\PersistDocumentLines;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Data\ScheduledInvoiceData;
use App\Modules\Invoices\Data\ScheduledInvoiceFailure;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final readonly class CreateScheduledInvoice
{
    public function __construct(
        private AllocatesDocumentNumbers $numbers,
        private InitializeDocumentDefaults $initializeDefaults,
        private ApplyDocumentCustomer $applyCustomer,
        private PersistDocumentLines $persistLines,
        private IssueLockedInvoice $issueInvoice,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        ScheduledInvoiceData $data,
        LockedDocumentConfiguration $configuration,
    ): Document {
        if (Document::query()->where('client_creation_key', $data->creationKey)->exists()) {
            throw new LogicException('A scheduled Invoice cannot exist without its occurrence.');
        }

        $issueDate = CarbonImmutable::parse($data->issueDate);
        $number = $this->numbers->next(DocumentKind::Invoice, $issueDate->year);
        $document = Document::query()->create([
            'kind' => DocumentKind::Invoice,
            'customer_id' => $data->customer->customerId,
            'rendered_number' => $number->rendered,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'number_series_id' => $number->seriesId,
            'number_period_key' => $number->periodKey,
            'number_sequence' => $number->sequence,
            'client_creation_key' => $data->creationKey,
            'issue_date' => $data->issueDate,
            'currency_code' => $data->customer->currencyCode,
            'currency_precision' => $data->customer->currencyPrecision,
            'document_language' => $data->customer->documentLanguage,
            'customer_reference' => $data->customerReference,
            'terms_and_conditions' => $data->termsAndConditions,
            'notes' => $data->notes,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);
        Invoice::query()->create([
            'document_id' => $document->id,
            'document_kind' => DocumentKind::Invoice,
            'lifecycle' => InvoiceLifecycle::Draft,
            'payment_term_days' => $data->paymentTermDays,
            'due_date' => $data->paymentTermDays === null ? null
                : DocumentCalendar::addDays($data->issueDate, $data->paymentTermDays),
        ]);
        $this->initializeDefaults->handle(
            $document,
            $configuration->settings,
            null,
            null,
            null,
        );
        $this->applyCustomer->handle($document, $data->customer);
        $this->applyBank($document, $data);
        $lines = $this->persistLines->handle($document, new Collection, $data->lines);

        if ($lines->completeLineCount !== count($data->lines)) {
            throw new LogicException('A scheduled Invoice requires complete lines.');
        }

        $document->update([
            'subtotal' => $lines->subtotal,
            'tax_total' => $lines->taxTotal,
            'total' => $lines->total,
        ]);
        $this->copyReminders($document, $data);
        $audit = $this->recordCreated($document, $data, count($data->lines));
        DocumentNumberEvent::query()->create([
            'document_id' => $document->id,
            'document_kind' => DocumentKind::Invoice,
            'rendered_number' => $number->rendered,
            'event_type' => DocumentNumberEventType::Assigned,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);

        $document->refresh();

        try {
            return $this->issueInvoice->handleScheduled(
                $document,
                $document->edit_version,
                $data->idempotencyReference,
            );
        } catch (InvoiceLifecycleException) {
            throw ScheduledInvoiceFailure::incomplete();
        }
    }

    private function applyBank(Document $document, ScheduledInvoiceData $data): void
    {
        $bank = $data->bank;

        if ($bank === null) {
            return;
        }

        DocumentBankSnapshot::query()->create([
            'document_id' => $document->id,
            'bank_account_id' => $bank->bankAccountId,
            'label' => $bank->label,
            'bank_name' => $bank->bankName,
            'account_holder' => $bank->accountHolder,
            'account_number' => $bank->accountNumber,
            'swift_bic' => $bank->swiftBic,
            'currency_code' => $bank->currencyCode,
            'local_routing_details' => $bank->localRoutingDetails,
        ]);
    }

    private function copyReminders(Document $document, ScheduledInvoiceData $data): void
    {
        foreach ($data->reminderRules as $rule) {
            DocumentReminderRule::query()->create([
                'invoice_id' => $document->id,
                'source_rule_id' => $rule->sourceRuleId,
                'relation' => $rule->relation,
                'day_offset' => $rule->dayOffset,
                'enabled' => $rule->enabled,
                'display_order' => $rule->displayOrder,
            ]);
        }
    }

    private function recordCreated(
        Document $document,
        ScheduledInvoiceData $data,
        int $lineCount,
    ): AuditEvent {
        return $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::ScheduledJob,
            actorReference: 'recurring_automation',
            action: 'company.invoice.created_from_recurring',
            targetType: 'Invoice',
            targetId: $document->id,
            idempotencyReference: $data->idempotencyReference,
            after: AuditPayload::fromAllowedFields([
                'assignment_source' => DocumentAssignmentSource::Automatic->value,
                'line_count' => $lineCount,
                'edit_version' => $document->edit_version,
            ], ['assignment_source', 'line_count', 'edit_version']),
        ));
    }
}
