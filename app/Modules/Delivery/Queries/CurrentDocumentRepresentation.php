<?php

namespace App\Modules\Delivery\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Companies\Queries\ResolveOutwardBrandTheme;
use App\Modules\Delivery\Data\OutwardDocument;
use App\Modules\Delivery\Support\OutwardDocumentFormatter;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteDisplayStatus;
use App\Modules\Quotes\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use RuntimeException;

final readonly class CurrentDocumentRepresentation
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private ResolveOutwardBrandTheme $brandTheme,
        private OutwardDocumentFormatter $format,
    ) {}

    public function forQuote(Company $company, User $actor, string $documentId): OutwardDocument
    {
        $document = $this->document($company, $actor, $documentId, DocumentKind::Quote);
        $quote = Quote::query()->whereKey($document->id)->firstOrFail();
        $settings = CompanySetting::query()->firstOrFail();
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();
        $status = QuoteDisplayStatus::resolve($quote->lifecycle, $quote->valid_until, $localDate);

        return $this->build(
            $company,
            $document,
            $this->translation('documents_outward.quote', $document->document_language ?? 'en'),
            $this->translation('documents_outward.statuses.'.$status->value, $document->document_language ?? 'en'),
            $quote->valid_until,
            null,
        );
    }

    public function forInvoice(Company $company, User $actor, string $documentId): OutwardDocument
    {
        $document = $this->document($company, $actor, $documentId, DocumentKind::Invoice);
        $invoice = Invoice::query()->whereKey($document->id)->firstOrFail();
        $locale = $document->document_language ?? 'en';

        return $this->build(
            $company,
            $document,
            $this->translation('documents_outward.invoice', $locale),
            $this->translation('documents_outward.statuses.'.$invoice->lifecycle->value, $locale),
            null,
            $invoice->due_date,
        );
    }

    private function document(
        Company $company,
        User $actor,
        string $documentId,
        DocumentKind $kind,
    ): Document {
        $ability = $kind === DocumentKind::Quote
            ? CompanyAbility::ViewQuotes
            : CompanyAbility::ViewInvoices;

        if (! $this->abilities->allows($actor, $company, $ability)) {
            throw new AuthorizationException;
        }

        return Document::query()
            ->whereKey($documentId)
            ->where('kind', $kind)
            ->firstOrFail();
    }

    private function build(
        Company $company,
        Document $document,
        string $kind,
        string $status,
        ?CarbonImmutable $validUntil,
        ?CarbonImmutable $dueDate,
    ): OutwardDocument {
        $companySnapshot = DocumentCompanySnapshot::query()
            ->where('document_id', $document->id)
            ->firstOrFail();
        $customerSnapshot = DocumentCustomerSnapshot::query()
            ->where('document_id', $document->id)
            ->first();
        $bankSnapshot = DocumentBankSnapshot::query()
            ->where('document_id', $document->id)
            ->first();
        $lines = DocumentLine::query()
            ->where('document_id', $document->id)
            ->orderBy('position')
            ->get();
        $locale = $document->document_language ?? 'en';
        $precision = $document->currency_precision ?? 2;
        $currency = $document->currency_code ?? '---';
        $theme = $this->brandTheme->for($companySnapshot->primary_brand_color);

        return new OutwardDocument(
            kind: $kind,
            number: $document->rendered_number,
            status: $status,
            language: $locale,
            issueDate: $this->format->date($document->issue_date, $locale),
            validUntil: $this->format->date($validUntil, $locale),
            dueDate: $this->format->date($dueDate, $locale),
            customerReference: $document->customer_reference,
            theme: [
                'accentColor' => $theme->accentColor,
                'onAccentColor' => $theme->onAccentColor,
                'textColor' => $theme->textColor,
                'ruleColor' => $theme->ruleColor,
            ],
            company: $this->company($companySnapshot),
            customer: $customerSnapshot === null ? null : $this->customer($customerSnapshot),
            lines: $this->lines(array_values($lines->all()), $precision, $currency, $companySnapshot, $locale),
            subtotal: $this->format->money($document->subtotal, $precision, $currency, $companySnapshot->currency_display_style, $locale),
            taxTotal: $this->format->money($document->tax_total, $precision, $currency, $companySnapshot->currency_display_style, $locale),
            total: $this->format->money($document->total, $precision, $currency, $companySnapshot->currency_display_style, $locale),
            bank: $this->bank($bankSnapshot, $locale),
            termsAndConditions: $document->terms_and_conditions,
            notes: $document->notes,
            hasLogo: $companySnapshot->logo_asset_id !== null,
            labels: $this->labels($locale),
        );
    }

    /** @return array{displayName: string, legalName: string|null, address: list<string>, registrations: list<string>, contacts: list<string>} */
    private function company(DocumentCompanySnapshot $snapshot): array
    {
        $displayName = $snapshot->trading_name ?? $snapshot->legal_name;

        return [
            'displayName' => $displayName,
            'legalName' => $displayName === $snapshot->legal_name ? null : $snapshot->legal_name,
            'address' => $this->address($snapshot),
            'registrations' => array_values(array_filter([
                $this->labelValue($snapshot->tax_registration_label, $snapshot->tax_registration_identifier),
                $this->labelValue($snapshot->business_registration_label, $snapshot->business_registration_number),
            ])),
            'contacts' => array_values(array_filter([$snapshot->email, $snapshot->phone, $snapshot->website])),
        ];
    }

    /** @return array{displayName: string, contact: list<string>, address: list<string>, registrations: list<string>, contacts: list<string>} */
    private function customer(DocumentCustomerSnapshot $snapshot): array
    {
        $displayName = $snapshot->legal_name ?? trim($snapshot->first_name.' '.$snapshot->last_name);

        return [
            'displayName' => $displayName,
            'contact' => array_values(array_filter([$snapshot->contact_name, $snapshot->contact_position_title])),
            'address' => $this->address($snapshot),
            'registrations' => array_values(array_filter([
                $this->labelValue($snapshot->tax_registration_label, $snapshot->tax_registration_identifier),
                $this->labelValue($snapshot->business_registration_label, $snapshot->business_registration_number),
            ])),
            'contacts' => array_values(array_filter([$snapshot->email, $snapshot->phone])),
        ];
    }

    /** @return list<string> */
    private function address(DocumentCompanySnapshot|DocumentCustomerSnapshot $snapshot): array
    {
        $locality = implode(', ', array_filter([$snapshot->postal_code, $snapshot->city, $snapshot->region]));

        return array_values(array_filter([
            $snapshot->address_line_1,
            $snapshot->address_line_2,
            $locality === '' ? null : $locality,
            $snapshot->country_code,
        ]));
    }

    /** @return list<array{label: string, value: string}> */
    private function bank(?DocumentBankSnapshot $bank, string $locale): array
    {
        if ($bank === null) {
            return [];
        }

        $rows = [
            ['label' => $this->translation('documents_outward.bank.bank_name', $locale), 'value' => $bank->bank_name],
            ['label' => $this->translation('documents_outward.bank.account_holder', $locale), 'value' => $bank->account_holder],
            ['label' => $this->translation('documents_outward.bank.account_number', $locale), 'value' => $bank->account_number],
        ];

        foreach (['swift_bic' => $bank->swift_bic, 'currency' => $bank->currency_code] as $key => $value) {
            if ($value !== null) {
                $rows[] = ['label' => $this->translation('documents_outward.bank.'.$key, $locale), 'value' => $value];
            }
        }

        foreach ($bank->local_routing_details ?? [] as $key => $value) {
            $rows[] = ['label' => $this->translation('companies_ui.settings.bank_accounts.routing_fields.'.$key, $locale), 'value' => $value];
        }

        return $rows;
    }

    private function labelValue(?string $label, ?string $value): ?string
    {
        return $label === null || $value === null ? null : "{$label}: {$value}";
    }

    /**
     * @param  list<DocumentLine>  $lines
     * @param  int<0, 8>  $precision
     * @return list<array{position: int, description: string, quantity: string, unitPrice: string, discount: string|null, tax: string|null, total: string}>
     */
    private function lines(
        array $lines,
        int $precision,
        string $currency,
        DocumentCompanySnapshot $company,
        string $locale,
    ): array {
        $rendered = [];
        $notSet = $this->translation('documents_outward.not_set', $locale);

        foreach ($lines as $line) {
            $rendered[] = [
                'position' => $line->position,
                'description' => $line->description ?? $notSet,
                'quantity' => $line->quantity === null
                    ? $notSet
                    : $this->format->quantity($line->quantity, $line->unit, $line->period_unit, $line->period_quantity, $locale),
                'unitPrice' => $line->item_price === null
                    ? $notSet
                    : $this->format->money($line->item_price, $precision, $currency, $company->currency_display_style, $locale),
                'discount' => DecimalRules::percentage($line->discount_percentage)->isZero()
                    ? null
                    : $this->format->decimal($line->discount_percentage, $locale).'%',
                'tax' => $line->tax_name === null
                    ? null
                    : $line->tax_name.' '.$this->format->decimal($line->tax_percentage, $locale).'%',
                'total' => $line->final_line_total === null
                    ? $notSet
                    : $this->format->money($line->final_line_total, $precision, $currency, $company->currency_display_style, $locale),
            ];
        }

        return $rendered;
    }

    /** @return array<string, string> */
    private function labels(string $locale): array
    {
        $labels = trans('documents_outward.labels', locale: $locale);

        if (! is_array($labels) || array_filter($labels, fn (mixed $value): bool => ! is_string($value)) !== []) {
            throw new RuntimeException('The outward document labels must resolve to strings.');
        }

        return $labels;
    }

    private function translation(string $key, string $locale): string
    {
        $translation = trans($key, locale: $locale);

        if (! is_string($translation)) {
            throw new RuntimeException("The outward document translation [{$key}] must be a string.");
        }

        return $translation;
    }
}
