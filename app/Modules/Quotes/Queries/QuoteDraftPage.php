<?php

namespace App\Modules\Quotes\Queries;

use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Catalog\Queries\CatalogFormOptions;
use App\Modules\Catalog\Queries\CatalogLineDefaults;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Queries\CustomerDocumentOptions;
use App\Modules\Customers\Queries\CustomerFormOptions;
use App\Modules\Delivery\Queries\DocumentDeliveryPage;
use App\Modules\Delivery\Queries\DocumentPublicLinkState;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Documents\Queries\DocumentCustomerSnapshotPage;
use App\Modules\Documents\Queries\DocumentDraftLinesPage;
use App\Modules\Quotes\Data\QuoteDisplayStatus;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class QuoteDraftPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerDocumentOptions $customerOptions,
        private CustomerFormOptions $customerForm,
        private CatalogFormOptions $catalogForm,
        private CatalogLineDefaults $catalogDefaults,
        private QuoteInvoiceAllocation $invoiceAllocation,
        private QuoteDeletionPreview $deletionPreview,
        private DocumentPublicLinkState $publicLinkState,
        private DocumentDeliveryPage $deliveryPage,
        private DocumentCustomerSnapshotPage $customerSnapshotPage,
        private DocumentDraftLinesPage $draftLinesPage,
    ) {}

    /** @return array<string, mixed> */
    public function create(Company $company, User $actor): array
    {
        $this->authorize($company, $actor);

        return [
            'storeUrl' => route('quotes.store', $company, false),
            'creationKey' => (string) Str::uuid7(),
        ];
    }

    /** @return array<string, mixed> */
    public function edit(
        Company $company,
        User $actor,
        string $documentId,
        string $locale,
        ?string $inlineCustomerId = null,
        ?string $inlineProductId = null,
    ): array {
        $this->authorize($company, $actor);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->firstOrFail();
        $quote = Quote::query()->whereKey($document->id)->firstOrFail();
        $settings = CompanySetting::query()->firstOrFail();
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();
        $displayStatus = QuoteDisplayStatus::resolve(
            $quote->lifecycle,
            $quote->valid_until,
            $localDate,
        );
        $lines = $this->draftLinesPage->for($document);
        $customer = $document->customer_id === null
            ? null
            : Customer::query()->whereKey($document->customer_id)->first();
        $taxDefault = DocumentTaxDefault::query()->where('document_id', $document->id)->first();
        $bank = DocumentBankSnapshot::query()->where('document_id', $document->id)->first();
        $delivery = DocumentDeliverySetting::query()->where('document_id', $document->id)->firstOrFail();
        $currencies = CompanyCurrency::query()
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('currency_code')
            ->get();
        $bankAccounts = BankAccount::query()
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
        $currencyOptions = $currencies->map(fn (CompanyCurrency $currency): array => [
            'value' => $currency->currency_code,
            'label' => $currency->currency_code,
            'precision' => $currency->currency_precision,
        ])->values()->all();
        $bankAccountOptions = $bankAccounts->map(fn (BankAccount $account): array => [
            'value' => $account->id,
            'label' => $account->label,
        ])->values()->all();
        $canDelete = $this->abilities->allows($actor, $company, CompanyAbility::DeleteQuotes);
        $deletion = $canDelete
            ? $this->deletionPreview->forDocuments([$document->id => $quote->lifecycle])[$document->id]
            : ['highRisk' => false, 'guard' => ['blocked' => false, 'description' => null]];

        if ($document->currency_code !== null
            && $currencies->firstWhere('currency_code', $document->currency_code) === null) {
            array_unshift($currencyOptions, [
                'value' => $document->currency_code,
                'label' => $document->currency_code,
                'precision' => $document->currency_precision,
            ]);
        }

        if ($bank !== null
            && $bank->bank_account_id !== null
            && $bankAccounts->firstWhere('id', $bank->bank_account_id) === null) {
            array_unshift($bankAccountOptions, [
                'value' => $bank->bank_account_id,
                'label' => $bank->label,
            ]);
        }

        return [
            'quote' => [
                'id' => $document->id,
                'number' => $document->rendered_number,
                'issueDate' => $document->issue_date?->toDateString(),
                'validityDays' => $quote->validity_days,
                'validUntil' => $quote->valid_until?->toDateString(),
                'customerReference' => $document->customer_reference,
                'lifecycle' => $quote->lifecycle->value,
                'status' => $displayStatus->value,
                'customer' => $customer === null ? null : [
                    'id' => $customer->id,
                    'displayName' => $customer->displayName(),
                    'snapshot' => $this->customerSnapshotPage->for($document->id),
                ],
                'currencyCode' => $document->currency_code,
                'currencyPrecision' => $document->currency_precision,
                'documentLanguage' => $document->document_language,
                'defaultsCustomized' => $document->defaults_customized,
                'termsAndConditions' => $document->terms_and_conditions,
                'notes' => $document->notes,
                'taxDefault' => $taxDefault === null ? null : [
                    'id' => $taxDefault->tax_preset_id,
                    'name' => $taxDefault->name,
                    'percentage' => rtrim(rtrim($taxDefault->percentage, '0'), '.') ?: '0',
                ],
                'bankAccount' => $bank === null ? null : [
                    'id' => $bank->bank_account_id,
                    'label' => $bank->label,
                    'currencyCode' => $bank->currency_code,
                ],
                'emailAttachmentMode' => $delivery->email_attachment_mode->value,
                'recipientCount' => $document->deliveryRecipients()->count(),
                'editVersion' => $document->edit_version,
                'subtotal' => $this->money($document->subtotal, $document->currency_precision),
                'taxTotal' => $this->money($document->tax_total, $document->currency_precision),
                'total' => $this->money($document->total, $document->currency_precision),
                'lines' => $lines,
            ],
            'updateUrl' => route('quotes.update', [$company, $document], false),
            'lifecycleUrl' => route('quotes.lifecycle.update', [$company, $document], false),
            'conversionUrl' => route('quotes.invoices.store', [$company, $document], false),
            'conversionKey' => (string) Str::uuid7(),
            'invoiceAllocation' => $this->invoiceAllocation->for(
                $company,
                $actor,
                $document->id,
                $document->total,
                $document->currency_precision ?? 0,
                $displayStatus,
            ),
            'deletion' => [
                'url' => $canDelete ? route('quotes.destroy', [$company, $document], false) : null,
                ...$deletion,
            ],
            'representationUrl' => route('quotes.current.show', [$company, $document], false),
            'pdfUrl' => route('quotes.current.pdf', [$company, $document], false),
            'publicLink' => $this->publicLinkState->for(
                $company,
                $actor,
                $document->id,
                DocumentKind::Quote,
            ),
            'directDelivery' => $this->deliveryPage->for(
                $company,
                $actor,
                $document->id,
                DocumentKind::Quote,
            ),
            'indexUrl' => route('quotes.index', $company, false),
            'quoteAbilities' => [
                'correctLifecycle' => $this->abilities->allows($actor, $company, CompanyAbility::ManageQuotes),
                'delete' => $canDelete,
            ],
            'sourceUrls' => [
                'customerSearch' => route('quote-sources.customers.index', $company, false),
                'companyCustomerDefaults' => route('quote-sources.customers.company-defaults', $company, false),
                'productSearch' => route('quote-sources.products.index', $company, false),
            ],
            'inlineCustomerStoreUrl' => route('quotes.inline-customers.store', [$company, $document], false),
            'inlineProductStoreUrl' => route('quotes.inline-products.store', [$company, $document], false),
            'inlineCreatedCustomer' => $inlineCustomerId === null
                ? null
                : $this->customerOptions->preview($company, $actor, $inlineCustomerId),
            'inlineCreatedProduct' => $inlineProductId === null
                ? null
                : $this->catalogDefaults->for(
                    $company,
                    $actor,
                    $inlineProductId,
                    $document->currency_code,
                ),
            'sourceAbilities' => [
                'createCustomer' => $this->abilities->allows($actor, $company, CompanyAbility::ManageCustomers),
                'createProduct' => $this->abilities->allows($actor, $company, CompanyAbility::ManageCatalog),
            ],
            'currencyOptions' => $currencyOptions,
            'languageOptions' => array_map(fn (string $language): array => [
                'value' => $language,
                'label' => __("companies_ui.settings.documents.language_options.{$language}"),
            ], SupportedLocales::all()),
            'bankAccountOptions' => $bankAccountOptions,
            'customerForm' => $this->customerForm->for($locale),
            'catalogForm' => $this->catalogForm->for(),
            'limits' => [
                'description' => DocumentFieldLimits::DESCRIPTION,
                'unit' => DocumentFieldLimits::UNIT,
                'taxName' => DocumentFieldLimits::TAX_NAME,
                'termsAndConditions' => DocumentContentLimits::TERMS_AND_CONDITIONS_CHARACTERS,
                'notes' => DocumentContentLimits::NOTES_CHARACTERS,
                'customerReference' => DocumentContentLimits::CUSTOMER_REFERENCE_CHARACTERS,
                'maxDayOffset' => DocumentContentLimits::MAX_CALENDAR_DAY_OFFSET,
            ],
        ];
    }

    private function authorize(Company $company, User $actor): void
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewQuotes)) {
            throw new AuthorizationException;
        }
    }

    /** @param int<0, 8>|null $precision */
    private function money(?string $value, ?int $precision): ?string
    {
        if ($value === null || $precision === null) {
            return $value;
        }

        return (string) DecimalRules::moneySource($value)->toScale($precision);
    }
}
