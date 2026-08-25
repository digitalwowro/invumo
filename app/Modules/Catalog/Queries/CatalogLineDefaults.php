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
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CatalogLineDefaults
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        string $productId,
        string $documentCurrencyCode,
    ): array {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCatalog)) {
            throw new AuthorizationException;
        }

        $product = ProductService::query()
            ->whereKey($productId)
            ->whereNull('archived_at')
            ->firstOrFail();
        $sourceCurrency = $product->currency_id === null
            ? null
            : CompanyCurrency::query()->whereKey($product->currency_id)->where('active', true)->firstOrFail();
        $documentCurrencyCode = strtoupper(trim($documentCurrencyCode));
        $priceMatches = $sourceCurrency instanceof CompanyCurrency
            && $sourceCurrency->currency_code === $documentCurrencyCode;
        $taxPreset = $product->tax_preset_id === null
            ? null
            : TaxPreset::query()->whereKey($product->tax_preset_id)->whereNull('archived_at')->firstOrFail();

        return [
            'sourceProductServiceId' => $product->id,
            'description' => $this->lineDescription($product),
            'unitPrice' => $priceMatches && $product->unit_price !== null
                ? (string) DecimalRules::moneySource($product->unit_price)->toScale(
                    DecimalRules::currencyPrecision($sourceCurrency->currency_precision),
                )
                : null,
            'priceStatus' => match (true) {
                $product->unit_price === null => 'ENTER_MANUALLY',
                $priceMatches => 'COPIED',
                default => 'CURRENCY_MISMATCH',
            },
            'sourceCurrencyCode' => $sourceCurrency?->currency_code,
            'unit' => $product->unit,
            'periodUnit' => $product->period_unit->value,
            'tax' => $taxPreset instanceof TaxPreset ? [
                'sourceTaxPresetId' => $taxPreset->id,
                'name' => $taxPreset->name,
                'percentage' => $taxPreset->percentage,
            ] : null,
        ];
    }

    private function lineDescription(ProductService $product): string
    {
        return $product->description === null
            ? $product->name
            : $product->name."\n".$product->description;
    }
}
