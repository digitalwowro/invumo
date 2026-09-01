<?php

namespace App\Modules\Invoices\Queries;

use App\Foundation\Documents\DocumentFieldLimits as ContentLimits;
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
use App\Modules\Delivery\Queries\InvoiceReminderPage;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Documents\Queries\DocumentCustomerSnapshotPage;
use App\Modules\Documents\Queries\DocumentDraftLinesPage;
use App\Modules\Invoices\Data\ResolvedInvoiceState;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Recurring\Queries\RecurringInvoiceAutomationState;
use App\Modules\Transactions\Queries\InvoiceTransactionsForInvoice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;

final readonly class InvoiceDraftPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerDocumentOptions $customerOptions,
        private CustomerFormOptions $customerForm,
        private CatalogFormOptions $catalogForm,
        private CatalogLineDefaults $catalogDefaults,
        private InvoiceTransactionsForInvoice $transactions,
        private InvoiceLifecycleActionsForInvoice $lifecycleActions,
        private InvoiceDeletionPreview $deletionPreview,
        private DocumentPublicLinkState $publicLinkState,
        private DocumentDeliveryPage $deliveryPage,
        private InvoiceReminderPage $reminderPage,
        private RecurringInvoiceAutomationState $recurringAutomation,
        private DocumentCustomerSnapshotPage $customerSnapshotPage,
        private DocumentDraftLinesPage $draftLinesPage,
    ) {}

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
            ->where('kind', DocumentKind::Invoice)
            ->firstOrFail();
        $invoice = Invoice::query()->whereKey($document->id)->firstOrFail();
        $canDelete = $this->abilities->allows($actor, $company, CompanyAbility::DeleteInvoices);
        $deletion = $canDelete
            ? $this->deletionPreview->for($document->id, $invoice->lifecycle)
            : ['highRisk' => false, 'guard' => ['blocked' => false, 'description' => null]];
        $settings = CompanySetting::query()->firstOrFail();
        $ledger = $this->transactions->ledger($document->id);
        $state = ResolvedInvoiceState::resolve(
            $invoice->lifecycle,
            $document->total,
            (string) $ledger->netPaid(),
            $invoice->due_date,
            Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay(),
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
            'invoice' => [
                'id' => $document->id,
                'number' => $document->rendered_number,
                'issueDate' => $document->issue_date?->toDateString(),
                'paymentTermDays' => $invoice->payment_term_days,
                'dueDate' => $invoice->due_date?->toDateString(),
                'customerReference' => $document->customer_reference,
                'lifecycle' => $invoice->lifecycle->value,
                'paymentState' => $state->paymentState?->value,
                'isOverdue' => $state->isOverdue,
                'displayStatus' => $state->displayStatus->value,
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
            'transactions' => $this->transactions->props(
                $company,
                $actor,
                $document->id,
                $invoice->lifecycle,
                $document->total,
                $document->currency_precision ?? 2,
            ),
            'lifecycleActions' => $this->lifecycleActions->props(
                $company,
                $actor,
                $document->id,
                $invoice->lifecycle,
                $ledger,
                $document->currency_precision ?? 2,
                $document->currency_code,
            ),
            'updateUrl' => route('invoices.update', [$company, $document], false),
            'issueUrl' => route('invoices.issue', [$company, $document], false),
            'representationUrl' => route('invoices.current.show', [$company, $document], false),
            'pdfUrl' => route('invoices.current.pdf', [$company, $document], false),
            'publicLink' => $this->publicLinkState->for(
                $company,
                $actor,
                $document->id,
                DocumentKind::Invoice,
            ),
            'directDelivery' => $this->deliveryPage->for(
                $company,
                $actor,
                $document->id,
                DocumentKind::Invoice,
            ),
            'reminders' => $this->reminderPage->for($company, $actor, $document),
            'recurringAutomation' => $this->recurringAutomation->for($company, $document),
            'deletion' => [
                'url' => $canDelete
                    ? route('invoices.destroy', [$company, $document], false)
                    : null,
                ...$deletion,
            ],
            'indexUrl' => route('invoices.index', $company, false),
            'sourceUrls' => [
                'customerSearch' => route('quote-sources.customers.index', $company, false),
                'companyCustomerDefaults' => route('quote-sources.customers.company-defaults', $company, false),
                'productSearch' => route('quote-sources.products.index', $company, false),
            ],
            'inlineCustomerStoreUrl' => route('invoices.inline-customers.store', [$company, $document], false),
            'inlineProductStoreUrl' => route('invoices.inline-products.store', [$company, $document], false),
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
                'termsAndConditions' => ContentLimits::TERMS_AND_CONDITIONS_CHARACTERS,
                'notes' => ContentLimits::NOTES_CHARACTERS,
                'customerReference' => ContentLimits::CUSTOMER_REFERENCE_CHARACTERS,
                'maxDayOffset' => ContentLimits::MAX_CALENDAR_DAY_OFFSET,
            ],
        ];
    }

    private function authorize(Company $company, User $actor): void
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageInvoices)) {
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
