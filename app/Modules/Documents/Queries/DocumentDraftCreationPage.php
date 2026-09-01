<?php

namespace App\Modules\Documents\Queries;

use App\Foundation\Documents\DocumentCalendar;
use App\Foundation\Documents\DocumentFieldLimits as ContentLimits;
use App\Foundation\Localization\SupportedLocales;
use App\Models\User;
use App\Modules\Catalog\Queries\CatalogFormOptions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Queries\CustomerFormOptions;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentFieldLimits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class DocumentDraftCreationPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerFormOptions $customerForm,
        private CatalogFormOptions $catalogForm,
    ) {}

    /** @return array<string, mixed> */
    public function quote(Company $company, User $actor, string $locale): array
    {
        $this->authorize($company, $actor, CompanyAbility::ManageQuotes);
        $page = $this->common($company, $locale);
        $settings = $page['settings'];
        $issueDate = $page['issueDate'];
        $validityDays = $settings->default_quote_validity_days;

        return [
            'quote' => [
                ...$page['draft'],
                'notes' => $settings->default_quote_notes,
                'validityDays' => $validityDays,
                'validUntil' => DocumentCalendar::addDays($issueDate, $validityDays),
                'lifecycle' => 'DRAFT',
                'status' => 'DRAFT',
            ],
            ...$page['props'],
            'creation' => [
                'url' => route('quotes.store', $company, false),
                'key' => (string) Str::uuid7(),
            ],
            'indexUrl' => route('quotes.index', $company, false),
        ];
    }

    /** @return array<string, mixed> */
    public function invoice(Company $company, User $actor, string $locale): array
    {
        $this->authorize($company, $actor, CompanyAbility::ManageInvoices);
        $page = $this->common($company, $locale);
        $settings = $page['settings'];
        $issueDate = $page['issueDate'];
        $paymentDays = $settings->default_payment_term_days;

        return [
            'invoice' => [
                ...$page['draft'],
                'notes' => $settings->default_invoice_notes,
                'paymentTermDays' => $paymentDays,
                'dueDate' => $paymentDays === null
                    ? null
                    : DocumentCalendar::addDays($issueDate, $paymentDays),
                'lifecycle' => 'DRAFT',
                'paymentState' => null,
                'isOverdue' => false,
                'displayStatus' => 'DRAFT',
            ],
            ...$page['props'],
            'creation' => [
                'url' => route('invoices.store', $company, false),
                'key' => (string) Str::uuid7(),
            ],
            'indexUrl' => route('invoices.index', $company, false),
        ];
    }

    /** @return array{settings: CompanySetting, issueDate: string, draft: array<string, mixed>, props: array<string, mixed>} */
    private function common(Company $company, string $locale): array
    {
        $settings = CompanySetting::query()->firstOrFail();

        if ($settings->timezone === null) {
            throw DocumentDraftFailure::configurationRequired();
        }

        $allCurrencies = CompanyCurrency::query()
            ->orderByDesc('is_default')
            ->orderBy('currency_code')
            ->get();
        $currencies = $allCurrencies->where('active', true)->values();
        $tax = TaxPreset::query()
            ->whereNull('archived_at')
            ->where('is_default', true)
            ->first();
        $banks = BankAccount::query()
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
        $currency = $currencies->firstWhere('is_default', true);
        $bank = $banks->firstWhere('is_default', true);
        $bankCurrency = $this->bankCurrency($bank, $allCurrencies);
        $issueDate = Date::now($settings->timezone)->toImmutable()->toDateString();

        return [
            'settings' => $settings,
            'issueDate' => $issueDate,
            'draft' => [
                'id' => '',
                'number' => '',
                'issueDate' => $issueDate,
                'customerReference' => null,
                'customer' => null,
                'currencyCode' => $currency?->currency_code,
                'currencyPrecision' => $currency?->currency_precision,
                'documentLanguage' => $settings->default_document_language,
                'defaultsCustomized' => false,
                'termsAndConditions' => $settings->default_terms_and_conditions,
                'notes' => null,
                'taxDefault' => $tax === null ? null : [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'percentage' => $this->compact($tax->percentage),
                ],
                'bankAccount' => $bank === null ? null : [
                    'id' => $bank->id,
                    'label' => $bank->label,
                    'currencyCode' => $bankCurrency?->currency_code,
                ],
                'emailAttachmentMode' => $settings->default_email_attachment_mode->value,
                'recipientCount' => 0,
                'editVersion' => 1,
                'subtotal' => '0',
                'taxTotal' => '0',
                'total' => '0',
                'lines' => [],
            ],
            'props' => [
                'sourceUrls' => [
                    'customerSearch' => route('quote-sources.customers.index', $company, false),
                    'companyCustomerDefaults' => route('quote-sources.customers.company-defaults', $company, false),
                    'productSearch' => route('quote-sources.products.index', $company, false),
                ],
                'inlineCustomerStoreUrl' => '',
                'inlineProductStoreUrl' => '',
                'inlineCreatedCustomer' => null,
                'inlineCreatedProduct' => null,
                'sourceAbilities' => ['createCustomer' => false, 'createProduct' => false],
                'currencyOptions' => $currencies->map(fn (CompanyCurrency $item): array => [
                    'value' => $item->currency_code,
                    'label' => $item->currency_code,
                    'precision' => $item->currency_precision,
                ])->values()->all(),
                'languageOptions' => array_map(fn (string $language): array => [
                    'value' => $language,
                    'label' => __("companies_ui.settings.documents.language_options.{$language}"),
                ], SupportedLocales::all()),
                'bankAccountOptions' => $banks->map(fn (BankAccount $item): array => [
                    'value' => $item->id,
                    'label' => $item->label,
                ])->values()->all(),
                'customerForm' => $this->customerForm->for($locale),
                'catalogForm' => $this->catalogForm->for(),
                'limits' => $this->limits(),
            ],
        ];
    }

    /** @param Collection<int, CompanyCurrency> $currencies */
    private function bankCurrency(?BankAccount $bank, Collection $currencies): ?CompanyCurrency
    {
        return $bank?->currency_id === null
            ? null
            : $currencies->firstWhere('id', $bank->currency_id);
    }

    /** @return array<string, int> */
    private function limits(): array
    {
        return [
            'description' => DocumentFieldLimits::DESCRIPTION,
            'unit' => DocumentFieldLimits::UNIT,
            'taxName' => DocumentFieldLimits::TAX_NAME,
            'termsAndConditions' => ContentLimits::TERMS_AND_CONDITIONS_CHARACTERS,
            'notes' => ContentLimits::NOTES_CHARACTERS,
            'customerReference' => ContentLimits::CUSTOMER_REFERENCE_CHARACTERS,
            'maxDayOffset' => ContentLimits::MAX_CALENDAR_DAY_OFFSET,
        ];
    }

    private function compact(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }

    private function authorize(Company $company, User $actor, CompanyAbility $ability): void
    {
        if (! $this->abilities->allows($actor, $company, $ability)) {
            throw new AuthorizationException;
        }
    }
}
