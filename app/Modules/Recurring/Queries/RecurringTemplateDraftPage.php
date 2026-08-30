<?php

namespace App\Modules\Recurring\Queries;

use App\Foundation\Documents\DocumentFieldLimits as DocumentContentLimits;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Catalog\Queries\CatalogFormOptions;
use App\Modules\Catalog\Queries\CatalogLineDefaults;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Queries\CustomerDocumentOptions;
use App\Modules\Customers\Queries\CustomerFormOptions;
use App\Modules\Documents\Data\DocumentFieldLimits;
use App\Modules\Recurring\Data\RecurringLineTaxMode;
use App\Modules\Recurring\Data\RecurringTemplateFieldLimits;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringOccurrence;
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
        private RecurringTemplateInheritancePage $inheritancePage,
        private RecurringTemplateDeletionPreview $deletionPreview,
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

        $resolvedCustomer = $this->customerOptions->resolved(
            $company, $actor, $template->customer_id,
        );
        $customer = $resolvedCustomer->preview();
        $inheritance = $this->inheritancePage->for($template, $resolvedCustomer);
        $lines = RecurringTemplateLine::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('position')
            ->get();
        $productNames = ProductService::query()
            ->whereIn('id', $lines->pluck('product_service_id')->filter())
            ->pluck('name', 'id');
        $occurrence = RecurringOccurrence::query()
            ->where('recurring_template_id', $template->id)
            ->orderByDesc('logical_ordinal')->first();
        $canManageAutomation = $template->state !== RecurringTemplateState::Completed
            && $this->abilities->allows(
                $actor, $company, CompanyAbility::ManageRecurringAutomation,
            );
        $canDelete = $this->abilities->allows(
            $actor, $company, CompanyAbility::DeleteRecurringTemplates,
        );
        $deletion = $canDelete
            ? $this->deletionPreview->forTemplates([$template->id => $template->state])[$template->id]
            : ['highRisk' => false, 'guard' => ['blocked' => false, 'description' => null]];

        return [
            'template' => [
                'id' => $template->id,
                'internalName' => $template->internal_name,
                'customerReference' => $template->customer_reference,
                'state' => $template->state->value,
                'editVersion' => $template->edit_version,
                'schedule' => [
                    'recurrenceKind' => $template->recurrence_kind?->value,
                    'customIntervalCount' => $template->custom_interval_count,
                    'customIntervalUnit' => $template->custom_interval_unit?->value,
                    'startDate' => $template->start_date?->toDateString(),
                    'endDate' => $template->end_date?->toDateString(),
                    'maximumOccurrenceCount' => $template->maximum_occurrence_count,
                    'nextOccurrenceDate' => $template->next_occurrence_date?->toDateString(),
                    'scheduleTimezone' => $template->schedule_timezone,
                    'scheduleLocalTime' => $template->schedule_local_time === null
                        ? null : substr($template->schedule_local_time, 0, 5),
                    'nextRunAt' => $template->next_run_at?->toISOString(),
                ],
                'execution' => [
                    'successfulOccurrenceCount' => $template->successful_occurrence_count,
                    'lastRunOutcome' => $template->last_run_outcome?->value,
                    'lastRunStartedAt' => $template->last_run_started_at?->toISOString(),
                    'lastRunCompletedAt' => $template->last_run_completed_at?->toISOString(),
                    'lastFailure' => $template->last_failure_category === null
                        ? null : __("recurring_ui.failures.{$template->last_failure_category}"),
                    'lastInvoiceUrl' => $occurrence === null
                        ? null : route('invoices.edit', [$company, $occurrence->invoice_id], false),
                ],
                'automation' => [
                    'automaticEmailEnabled' => $template->automatic_email_enabled,
                    'lastConfirmedCurrency' => $template->last_confirmed_delivery_currency,
                    'currencyReviewRequired' => $template->currency_review_required,
                    'currencyReviewCurrency' => $template->currency_review_currency,
                ],
                'customer' => $customer,
                'currencyCode' => $inheritance['inheritance']['currencyCode'],
                'currencyPrecision' => $inheritance['inheritance']['currencyPrecision'],
                'lines' => $lines->map(fn (RecurringTemplateLine $line): array => [
                    'id' => $line->id,
                    'productServiceId' => $line->product_service_id,
                    'productServiceName' => $productNames->get($line->product_service_id),
                    'description' => $line->description,
                    'itemPrice' => $line->item_price,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'periodUnit' => $line->period_unit->value,
                    'periodQuantity' => $line->period_quantity,
                    'discountPercentage' => $line->discount_percentage,
                    'taxName' => match ($line->tax_mode) {
                        RecurringLineTaxMode::InheritCustomer => $customer['taxDefault']['name'] ?? null,
                        RecurringLineTaxMode::None => null,
                        RecurringLineTaxMode::Explicit => $line->tax_name,
                    },
                    'taxPercentage' => match ($line->tax_mode) {
                        RecurringLineTaxMode::InheritCustomer => $customer['taxDefault']['percentage'] ?? '0',
                        RecurringLineTaxMode::None => '0',
                        RecurringLineTaxMode::Explicit => $line->tax_percentage,
                    },
                    'taxPresetId' => $line->tax_preset_id,
                    'taxMode' => $line->tax_mode->value,
                    'finalLineTotal' => null,
                ])->values()->all(),
            ],
            'updateUrl' => route('recurring.update', [$company, $template], false),
            'scheduleUpdateUrl' => route('recurring.schedule.update', [$company, $template], false),
            'automaticEmailUpdateUrl' => route(
                'recurring.automatic-email.update', [$company, $template], false,
            ),
            'transitionUrls' => [
                'activate' => route('recurring.transition', [$company, $template, 'activate'], false),
                'pause' => route('recurring.transition', [$company, $template, 'pause'], false),
                'resume' => route('recurring.transition', [$company, $template, 'resume'], false),
                'complete' => route('recurring.transition', [$company, $template, 'complete'], false),
            ],
            'duplicateUrl' => route('recurring.duplicate', [$company, $template], false),
            'retryUrl' => route('recurring.retry', [$company, $template], false),
            'duplicateCreationKey' => (string) Str::uuid7(),
            'deleteUrl' => route('recurring.destroy', [$company, $template], false),
            'deletion' => $deletion,
            'indexUrl' => route('recurring.index', $company, false),
            'canDelete' => $canDelete,
            'canEditDraft' => $template->state === RecurringTemplateState::Draft,
            'canManageSchedule' => match ($template->state) {
                RecurringTemplateState::Draft => $this->abilities->allows(
                    $actor, $company, CompanyAbility::ManageRecurringDrafts,
                ),
                RecurringTemplateState::Active, RecurringTemplateState::Paused => $this->abilities->allows(
                    $actor, $company, CompanyAbility::ManageRecurringAutomation,
                ),
                RecurringTemplateState::Completed => false,
            },
            'canManageAutomation' => $canManageAutomation,
            'canRetry' => $canManageAutomation && $template->last_run_outcome?->value === 'FAILED',
            'canDuplicate' => $template->state === RecurringTemplateState::Completed,
            ...$inheritance,
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
                    $company, $actor, $inlineProductId,
                    $inheritance['inheritance']['currencyCode'],
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
                'termsAndConditions' => DocumentContentLimits::TERMS_AND_CONDITIONS_CHARACTERS,
                'notes' => DocumentContentLimits::NOTES_CHARACTERS,
                'maxDayOffset' => DocumentContentLimits::MAX_CALENDAR_DAY_OFFSET,
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
