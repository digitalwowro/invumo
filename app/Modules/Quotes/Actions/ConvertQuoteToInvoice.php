<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\CopyDocumentSnapshots;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteConversionData;
use App\Modules\Quotes\Data\QuoteDisplayStatus;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Exceptions\QuoteConversionException;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ConvertQuoteToInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockDocumentConfiguration $lockConfiguration,
        private AllocatesDocumentNumbers $numbers,
        private CopyDocumentSnapshots $copySnapshots,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $quoteId,
        QuoteConversionData $data,
    ): Document {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->convert($company, $actor, $quoteId, $data),
                3,
            ),
        );
    }

    private function convert(
        Company $company,
        User $actor,
        string $quoteId,
        QuoteConversionData $data,
    ): Document {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageInvoices);
        $configuration = $this->lockConfiguration->handle();
        $settings = $configuration->settings;

        $existing = QuoteInvoiceLink::query()
            ->where('quote_id', $quoteId)
            ->where('creation_key', $data->creationKey)
            ->first();

        if ($existing instanceof QuoteInvoiceLink) {
            return Document::query()->whereKey($existing->invoice_id)->firstOrFail();
        }

        if (Document::query()
            ->where('kind', DocumentKind::Invoice)
            ->where('client_creation_key', $data->creationKey)
            ->exists()) {
            throw QuoteConversionException::idempotencyConflict();
        }

        if ($settings->timezone === null) {
            throw QuoteConversionException::sourceInvalid();
        }

        $localDate = Date::now($settings->timezone)->toImmutable()->startOfDay();
        $number = $this->numbers->next(DocumentKind::Invoice, $localDate->year);
        $source = Document::query()
            ->whereKey($quoteId)
            ->where('kind', DocumentKind::Quote)
            ->lockForUpdate()
            ->firstOrFail();
        $quote = Quote::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
        QuoteInvoiceLink::query()
            ->where('quote_id', $source->id)->orderBy('id')->lockForUpdate()->get();
        $status = QuoteDisplayStatus::resolve($quote->lifecycle, $quote->valid_until, $localDate);
        $this->assertEligible($company, $actor, $quote->lifecycle, $status, $data);

        $invoiceDocument = Document::query()->create([
            'kind' => DocumentKind::Invoice,
            'customer_id' => $source->customer_id,
            'rendered_number' => $number->rendered,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'number_series_id' => $number->seriesId,
            'number_period_key' => $number->periodKey,
            'number_sequence' => $number->sequence,
            'client_creation_key' => $data->creationKey,
            'issue_date' => $localDate->toDateString(),
            'currency_code' => $source->currency_code,
            'currency_precision' => $source->currency_precision,
            'document_language' => $source->document_language,
            'customer_reference' => $source->customer_reference,
            'terms_and_conditions' => $source->terms_and_conditions,
            'notes' => $settings->default_invoice_notes,
            'defaults_customized' => $source->defaults_customized,
            'subtotal' => $source->subtotal,
            'tax_total' => $source->tax_total,
            'total' => $source->total,
        ]);
        $paymentTermDays = $quote->invoice_payment_term_days;
        Invoice::query()->create([
            'document_id' => $invoiceDocument->id,
            'document_kind' => DocumentKind::Invoice,
            'lifecycle' => InvoiceLifecycle::Draft,
            'payment_term_days' => $paymentTermDays,
            'due_date' => $paymentTermDays === null
                ? null
                : DocumentCalendar::addDays($localDate->toDateString(), $paymentTermDays),
        ]);
        $lineCount = $this->copySnapshots->handle(
            $source,
            $invoiceDocument,
            $settings->public_links_enabled_by_default,
        );
        QuoteInvoiceLink::query()->create([
            'quote_id' => $source->id,
            'invoice_id' => $invoiceDocument->id,
            'copied_by_user_id' => $actor->id,
            'creation_key' => $data->creationKey,
            'copied_at' => now(),
        ]);

        $audit = $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.invoice.created_from_quote',
            targetType: 'Invoice',
            targetId: $invoiceDocument->id,
            idempotencyReference: $data->creationKey,
            after: AuditPayload::fromAllowedFields([
                'quote_id' => $source->id,
                'line_count' => $lineCount,
                'override_confirmed' => $status !== QuoteDisplayStatus::Accepted,
                'edit_version' => 1,
            ], ['quote_id', 'line_count', 'override_confirmed', 'edit_version']),
        ));
        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.converted_to_invoice',
            targetType: 'Quote',
            targetId: $source->id,
            idempotencyReference: $data->creationKey,
            after: AuditPayload::fromAllowedFields([
                'invoice_id' => $invoiceDocument->id,
                'override_confirmed' => $status !== QuoteDisplayStatus::Accepted,
            ], ['invoice_id', 'override_confirmed']),
        ));
        DocumentNumberEvent::query()->create([
            'document_id' => $invoiceDocument->id,
            'document_kind' => DocumentKind::Invoice,
            'rendered_number' => $number->rendered,
            'event_type' => DocumentNumberEventType::Assigned,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);

        return $invoiceDocument->refresh();
    }

    private function assertEligible(
        Company $company,
        User $actor,
        QuoteLifecycle $lifecycle,
        QuoteDisplayStatus $status,
        QuoteConversionData $data,
    ): void {
        if ($lifecycle === QuoteLifecycle::Rejected) {
            throw QuoteConversionException::rejected();
        }

        if ($status === QuoteDisplayStatus::Accepted) {
            return;
        }

        $this->authorizer->authorize($actor, $company, CompanyAbility::OverrideQuoteConversion);

        if (! $data->confirmedOverride) {
            throw QuoteConversionException::confirmationRequired();
        }
    }
}
