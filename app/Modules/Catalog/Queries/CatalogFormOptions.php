<?php

namespace App\Modules\Catalog\Queries;

use App\Foundation\Money\PeriodUnit;
use App\Modules\Catalog\Data\CatalogFieldLimits;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;

final readonly class CatalogFormOptions
{
    /** @return array<string, mixed> */
    public function for(): array
    {
        return [
            'currencyOptions' => CompanyCurrency::query()
                ->where('active', true)
                ->orderByDesc('is_default')
                ->orderBy('currency_code')
                ->get()
                ->map(fn (CompanyCurrency $currency): array => [
                    'value' => $currency->id,
                    'label' => $currency->currency_code,
                    'code' => $currency->currency_code,
                    'precision' => $currency->currency_precision,
                ])->values()->all(),
            'taxPresetOptions' => TaxPreset::query()
                ->whereNull('archived_at')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (TaxPreset $preset): array => [
                    'value' => $preset->id,
                    'label' => $preset->name,
                    'percentage' => rtrim(rtrim($preset->percentage, '0'), '.'),
                ])->values()->all(),
            'periodOptions' => array_map(fn (PeriodUnit $period): array => [
                'value' => $period->value,
                'label' => __("catalog_ui.form.periods.{$period->value}"),
            ], PeriodUnit::cases()),
            'limits' => [
                'name' => CatalogFieldLimits::NAME,
                'description' => CatalogFieldLimits::DESCRIPTION,
                'code' => CatalogFieldLimits::CODE,
                'unit' => CatalogFieldLimits::UNIT,
            ],
        ];
    }
}
