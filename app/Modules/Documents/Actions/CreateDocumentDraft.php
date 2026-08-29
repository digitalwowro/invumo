<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Documents\Contracts\AllocatesDocumentNumbers;
use App\Modules\Documents\Data\CreatedDocumentDraft;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\Date;

final readonly class CreateDocumentDraft
{
    public function __construct(
        private AllocatesDocumentNumbers $numbers,
        private InitializeDocumentDefaults $initializeDefaults,
    ) {}

    public function handle(
        DocumentKind $kind,
        string $creationKey,
        LockedDocumentConfiguration $configuration,
    ): CreatedDocumentDraft {
        $settings = $configuration->settings;

        if ($settings->timezone === null) {
            throw DocumentDraftFailure::configurationRequired();
        }

        $currency = $configuration->currencies
            ->where('active', true)
            ->firstWhere('is_default', true);
        $taxPreset = $configuration->taxPresets
            ->whereNull('archived_at')
            ->firstWhere('is_default', true);
        $bankAccount = $configuration->bankAccounts
            ->whereNull('archived_at')
            ->firstWhere('is_default', true);
        $bankCurrency = $bankAccount?->currency_id === null
            ? null
            : $configuration->currencies->firstWhere('id', $bankAccount->currency_id);
        $localDate = Date::now($settings->timezone)->toImmutable()->startOfDay();
        $number = $this->numbers->next($kind, $localDate->year);
        $document = Document::query()->create([
            'kind' => $kind,
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
            'customer_reference' => null,
            'terms_and_conditions' => $settings->default_terms_and_conditions,
            'notes' => match ($kind) {
                DocumentKind::Quote => $settings->default_quote_notes,
                DocumentKind::Invoice => $settings->default_invoice_notes,
            },
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        $this->initializeDefaults->handle(
            $document,
            $settings,
            $taxPreset,
            $bankAccount,
            $bankCurrency instanceof CompanyCurrency ? $bankCurrency : null,
        );

        return new CreatedDocumentDraft($document, $settings, $localDate->toDateString());
    }
}
