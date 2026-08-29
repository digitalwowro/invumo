<?php

namespace App\Modules\Recurring\Queries;

use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Models\User;
use App\Modules\Catalog\Queries\CatalogFormOptions;
use App\Modules\Catalog\Queries\CatalogLineDefaults;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Queries\CustomerDocumentOptions;
use App\Modules\Customers\Queries\CustomerFormOptions;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Recurring\Data\RecurringTemplateFieldLimits;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final readonly class RecurringTemplateDraftPage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerDocumentOptions $customerOptions,
        private CustomerFormOptions $customerForm,
        private CatalogFormOptions $catalogForm,
        private CatalogLineDefaults $catalogDefaults,
    ) {}

    /** @return array<string, mixed> */
    public function create(
        Company $company,
        User $actor,
        string $locale,
        ?string $inlineCustomerId = null,
    ): array {
        $this->authorize($company, $actor);

        return [
            'storeUrl' => route('recurring.store', $company, false),
            'creationKey' => (string) Str::uuid7(),
            'sourceUrls' => $this->sourceUrls($company),
            'inlineCustomerStoreUrl' => route('recurring.inline-customers.store', $company, false),
            'inlineCreatedCustomer' => $inlineCustomerId === null
                ? null
                : $this->customerOptions->preview($company, $actor, $inlineCustomerId),
            'sourceAbilities' => [
                'createCustomer' => $this->abilities->allows(
                    $actor, $company, CompanyAbility::ManageCustomers,
                ),
            ],
            'customerForm' => $this->customerForm->for($locale),
            'limits' => ['internalName' => RecurringTemplateFieldLimits::INTERNAL_NAME],
        ];
    }

    /** @return array<string, mixed> */
    public function edit(
        Company $company,
        User $actor,
        string $templateId,
        string $locale,
        ?string $inlineCustomerId = null,
        ?string $inlineProductId = null,
    ): array {
        $this->authorize($company, $actor);
        $template = RecurringTemplate::query()->whereKey($templateId)->firstOrFail();

        if ($template->state !== RecurringTemplateState::Draft) {
            throw new AuthorizationException;
        }

        $customer = $this->customerOptions->preview($company, $actor, $template->customer_id);
        $lines = RecurringTemplateLine::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('position')
            ->get();

        return [
            'template' => [
                'id' => $template->id,
                'internalName' => $template->internal_name,
                'customerReference' => $template->customer_reference,
                'state' => $template->state->value,
                'editVersion' => $template->edit_version,
                'customer' => $customer,
                'currencyCode' => $customer['currencyCode'],
                'currencyPrecision' => $customer['currencyPrecision'],
                'lines' => $lines->map(fn (RecurringTemplateLine $line): array => [
                    'id' => $line->id,
                    'productServiceId' => $line->product_service_id,
                    'description' => $line->description,
                    'itemPrice' => $line->item_price,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'periodUnit' => $line->period_unit->value,
                    'periodQuantity' => $line->period_quantity,
                    'discountPercentage' => $line->discount_percentage,
                    'taxName' => $line->tax_name,
                    'taxPercentage' => $line->tax_percentage,
                    'taxPresetId' => null,
                    'finalLineTotal' => null,
                ])->values()->all(),
            ],
            'updateUrl' => route('recurring.update', [$company, $template], false),
            'deleteUrl' => route('recurring.destroy', [$company, $template], false),
            'indexUrl' => route('recurring.index', $company, false),
            'canDelete' => $this->abilities->allows(
                $actor, $company, CompanyAbility::DeleteRecurringTemplates,
            ),
            'sourceUrls' => $this->sourceUrls($company),
            'inlineCustomerStoreUrl' => route(
                'recurring.inline-customers.store', $company, false,
            ),
            'inlineProductStoreUrl' => route(
                'recurring.inline-products.store', [$company, $template], false,
            ),
            'inlineCreatedCustomer' => $inlineCustomerId === null
                ? null
                : $this->customerOptions->preview($company, $actor, $inlineCustomerId),
            'inlineCreatedProduct' => $inlineProductId === null
                ? null
                : $this->catalogDefaults->for(
                    $company, $actor, $inlineProductId, $customer['currencyCode'],
                ),
            'sourceAbilities' => [
                'createCustomer' => $this->abilities->allows(
                    $actor, $company, CompanyAbility::ManageCustomers,
                ),
                'createProduct' => $this->abilities->allows(
                    $actor, $company, CompanyAbility::ManageCatalog,
                ),
            ],
            'customerForm' => $this->customerForm->for($locale),
            'catalogForm' => $this->catalogForm->for(),
            'limits' => [
                'internalName' => RecurringTemplateFieldLimits::INTERNAL_NAME,
                'customerReference' => DocumentContentLimits::CUSTOMER_REFERENCE_CHARACTERS,
                'description' => DocumentFieldLimits::DESCRIPTION,
                'unit' => DocumentFieldLimits::UNIT,
                'taxName' => DocumentFieldLimits::TAX_NAME,
            ],
        ];
    }

    private function authorize(Company $company, User $actor): void
    {
        if (! $this->abilities->allows(
            $actor, $company, CompanyAbility::ManageRecurringDrafts,
        )) {
            throw new AuthorizationException;
        }
    }

    /** @return array<string, string> */
    private function sourceUrls(Company $company): array
    {
        return [
            'customerSearch' => route('quote-sources.customers.index', $company, false),
            'companyCustomerDefaults' => route(
                'quote-sources.customers.company-defaults', $company, false,
            ),
            'productSearch' => route('quote-sources.products.index', $company, false),
        ];
    }
}
