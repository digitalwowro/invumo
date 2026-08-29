<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Policies\CompanyAuthorization;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyTaxPresetsPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{taxPresets: list<array<string, mixed>>} */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (
            $membership === null
            || ! $this->authorization->allows(
                $membership->role,
                CompanyAbility::ManageCompanySettings,
            )
        ) {
            throw new AuthorizationException;
        }

        $presets = TaxPreset::query()
            ->select('tax_presets.*')
            ->addSelect([
                'customer_reference_count' => Customer::query()->selectRaw('count(*)')
                    ->whereColumn('customers.tax_preset_id', 'tax_presets.id'),
                'product_reference_count' => ProductService::query()->selectRaw('count(*)')
                    ->whereColumn('products_services.tax_preset_id', 'tax_presets.id'),
                'document_line_reference_count' => DocumentLine::query()->selectRaw('count(*)')
                    ->whereColumn('document_lines.tax_preset_id', 'tax_presets.id'),
                'document_default_reference_count' => DocumentTaxDefault::query()->selectRaw('count(*)')
                    ->whereColumn('document_tax_defaults.tax_preset_id', 'tax_presets.id'),
                'template_reference_count' => RecurringTemplateCustomerValue::query()->selectRaw('count(*)')
                    ->whereColumn('recurring_template_customer_values.tax_preset_id', 'tax_presets.id'),
                'template_line_reference_count' => RecurringTemplateLine::query()->selectRaw('count(*)')
                    ->whereColumn('recurring_template_lines.tax_preset_id', 'tax_presets.id'),
            ])
            ->orderByRaw('archived_at ASC NULLS FIRST')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (TaxPreset $preset): array => $this->row($company, $preset))
            ->all();

        return ['taxPresets' => array_values($presets)];
    }

    /** @return array<string, mixed> */
    private function row(Company $company, TaxPreset $preset): array
    {
        $customerCount = (int) $preset->getAttribute('customer_reference_count');
        $productCount = (int) $preset->getAttribute('product_reference_count');
        $documentCount = (int) $preset->getAttribute('document_line_reference_count')
            + (int) $preset->getAttribute('document_default_reference_count');
        $templateCount = (int) $preset->getAttribute('template_reference_count')
            + (int) $preset->getAttribute('template_line_reference_count');

        return [
            'id' => $preset->id,
            'name' => $preset->name,
            'percentage' => $this->displayPercentage($preset->percentage),
            'isDefault' => $preset->is_default,
            'archived' => $preset->archived_at !== null,
            'updateUrl' => $preset->archived_at === null
                ? route('company-tax-presets.update', [$company, $preset], false)
                : null,
            'archiveUrl' => $preset->archived_at === null
                ? route('company-tax-presets.archive', [$company, $preset], false)
                : null,
            'restoreUrl' => $preset->archived_at !== null
                ? route('company-tax-presets.restore', [$company, $preset], false)
                : null,
            'deleteUrl' => route('company-tax-presets.destroy', [$company, $preset], false),
            'archiveGuard' => $this->archiveGuard($customerCount, $productCount),
            'deleteGuard' => $this->deleteGuard(
                $customerCount,
                $productCount,
                $documentCount,
                $templateCount,
            ),
        ];
    }

    /** @return array{blocked: bool, description: string|null} */
    private function archiveGuard(int $customers, int $products): array
    {
        $blocked = $customers + $products > 0;

        return [
            'blocked' => $blocked,
            'description' => $blocked ? trans_choice('companies_ui.settings.taxes.archive_dependency_description', 1, [
                'customers' => $customers,
                'products' => $products,
            ]) : null,
        ];
    }

    /** @return array{blocked: bool, description: string|null} */
    private function deleteGuard(int $customers, int $products, int $documents, int $templates): array
    {
        $blocked = $customers + $products + $documents + $templates > 0;

        return [
            'blocked' => $blocked,
            'description' => $blocked ? trans_choice('companies_ui.settings.taxes.delete_dependency_description', 1, [
                'customers' => $customers,
                'products' => $products,
                'documents' => $documents,
                'templates' => $templates,
            ]) : null,
        ];
    }

    private function displayPercentage(string $percentage): string
    {
        $trimmed = rtrim(rtrim($percentage, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
