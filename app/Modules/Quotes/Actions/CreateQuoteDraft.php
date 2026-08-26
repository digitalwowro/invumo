<?php

namespace App\Modules\Quotes\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Documents\Actions\InitializeDocumentDefaults;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\DocumentNumberEventType;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Exceptions\QuoteDraftException;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CreateQuoteDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private AllocatesDocumentNumbers $numbers,
        private InitializeDocumentDefaults $initializeDefaults,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, string $creationKey): Document
    {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): Document => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): Document => $this->create($company, $actor, $creationKey),
                3,
            ),
        );
    }

    private function create(Company $company, User $actor, string $creationKey): Document
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageQuotes);

        $existing = Document::query()
            ->where('kind', DocumentKind::Quote)
            ->where('client_creation_key', $creationKey)
            ->first();

        if ($existing instanceof Document) {
            return $existing;
        }

        $settings = CompanySetting::query()->orderBy('id')->lockForUpdate()->first();
        $currencies = CompanyCurrency::query()->orderBy('id')->lockForUpdate()->get();
        $taxPresets = TaxPreset::query()->orderBy('id')->lockForUpdate()->get();
        $bankAccounts = BankAccount::query()->orderBy('id')->lockForUpdate()->get();
        $currency = $currencies
            ->where('active', true)
            ->firstWhere('is_default', true);
        $taxPreset = $taxPresets
            ->whereNull('archived_at')
            ->firstWhere('is_default', true);
        $bankAccount = $bankAccounts
            ->whereNull('archived_at')
            ->firstWhere('is_default', true);
        $bankCurrency = $bankAccount?->currency_id === null
            ? null
            : $currencies->firstWhere('id', $bankAccount->currency_id);

        if (! $settings instanceof CompanySetting || $settings->timezone === null) {
            throw QuoteDraftException::configurationRequired();
        }

        $localDate = Date::now($settings->timezone)->toImmutable()->startOfDay();
        $number = $this->numbers->next(DocumentKind::Quote, $localDate->year);
        $document = Document::query()->create([
            'kind' => DocumentKind::Quote,
            'rendered_number' => $number->rendered,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'number_series_id' => $number->seriesId,
            'number_period_key' => $number->periodKey,
            'number_sequence' => $number->sequence,
            'client_creation_key' => $creationKey,
            'issue_date' => $localDate->toDateString(),
            'currency_code' => $currency?->currency_code,
            'currency_precision' => $currency?->currency_precision,
            'document_language' => $settings->default_document_language,
            'terms_and_conditions' => $settings->default_terms_and_conditions,
            'notes' => $settings->default_quote_notes,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        Quote::query()->create([
            'document_id' => $document->id,
            'document_kind' => DocumentKind::Quote,
            'lifecycle' => QuoteLifecycle::Draft,
        ]);

        $this->initializeDefaults->handle(
            $document,
            $settings,
            $taxPreset,
            $bankAccount,
            $bankCurrency,
        );

        $audit = $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.quote.created',
            targetType: 'Quote',
            targetId: $document->id,
            idempotencyReference: $creationKey,
            after: AuditPayload::fromAllowedFields([
                'assignment_source' => DocumentAssignmentSource::Automatic->value,
                'has_currency' => $currency !== null,
                'edit_version' => 1,
            ], ['assignment_source', 'has_currency', 'edit_version']),
        ));

        DocumentNumberEvent::query()->create([
            'document_id' => $document->id,
            'document_kind' => DocumentKind::Quote,
            'rendered_number' => $number->rendered,
            'event_type' => DocumentNumberEventType::Assigned,
            'assignment_source' => DocumentAssignmentSource::Automatic,
            'occurred_at' => now(),
            'related_audit_event_id' => $audit->id,
        ]);

        return $document->refresh();
    }
}
