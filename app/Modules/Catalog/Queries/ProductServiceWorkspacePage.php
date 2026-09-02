<?php

namespace App\Modules\Catalog\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ProductServiceWorkspacePage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CatalogFormOptions $options,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, string $productId): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageCatalog)) {
            throw new AuthorizationException;
        }

        $product = ProductService::query()->findOrFail($productId);
        $currency = $product->currency_id === null
            ? null
            : CompanyCurrency::query()->find($product->currency_id);
        $taxPreset = $product->tax_preset_id === null
            ? null
            : TaxPreset::query()->find($product->tax_preset_id);
        $documentCount = DocumentLine::query()
            ->where('product_service_id', $product->id)
            ->count();
        $templateCount = RecurringTemplateLine::query()
            ->where('product_service_id', $product->id)
            ->count();
        $archived = $product->archived_at !== null;

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'internalCode' => $product->internal_code,
                'unitPrice' => $this->price($product, $currency),
                'currencyId' => $product->currency_id,
                'currencyCode' => $currency?->currency_code,
                'unit' => $product->unit,
                'periodUnit' => $product->period_unit->value,
                'periodLabel' => __("catalog_ui.form.periods.{$product->period_unit->value}"),
                'taxPresetId' => $product->tax_preset_id,
                'taxPresetName' => $taxPreset?->name,
                'archived' => $archived,
            ],
            'indexUrl' => route('catalog.index', $company, false),
            'workspaceUrl' => route('catalog.show', [$company, $product], false),
            'updateUrl' => $archived
                ? null
                : route('catalog.update', [$company, $product], false),
            'archiveUrl' => $archived
                ? null
                : route('catalog.archive', [$company, $product], false),
            'restoreUrl' => $archived
                ? route('catalog.restore', [$company, $product], false)
                : null,
            'deleteUrl' => route('catalog.destroy', [$company, $product], false),
            'deleteGuard' => [
                'blocked' => $documentCount + $templateCount > 0,
                'description' => $documentCount + $templateCount > 0
                    ? __('catalog_ui.actions.delete_dependency_description', [
                        'documents' => $documentCount,
                        'templates' => $templateCount,
                    ])
                    : null,
            ],
            ...$this->options->for(),
        ];
    }

    private function price(
        ProductService $product,
        ?CompanyCurrency $currency,
    ): ?string {
        if ($product->unit_price === null || $currency === null) {
            return null;
        }

        return (string) DecimalRules::moneySource($product->unit_price)
            ->toScale(DecimalRules::currencyPrecision($currency->currency_precision));
    }
}
