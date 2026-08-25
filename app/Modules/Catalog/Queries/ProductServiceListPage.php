<?php

namespace App\Modules\Catalog\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Catalog\Http\Requests\ProductServiceListRequest;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class ProductServiceListPage
{
    private const SEARCH_EXPRESSION = "coalesce(name, '') || ' ' || coalesce(internal_code, '') || ' ' || coalesce(description, '')";

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CatalogFormOptions $options,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, ProductServiceListRequest $request): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageCatalog)) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $query = ProductService::query();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();
        $currencies = CompanyCurrency::query()->get()->keyBy('id');
        $taxPresets = TaxPreset::query()->get()->keyBy('id');

        return [
            'products' => [
                'items' => array_values(array_map(
                    fn (ProductService $product): array => $this->row($company, $product, $currencies, $taxPresets),
                    $page->items(),
                )),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'indexUrl' => route('catalog.index', $company, false),
            'storeUrl' => route('catalog.store', $company, false),
            ...$this->options->for(),
        ];
    }

    /**
     * @param  Builder<ProductService>  $query
     * @param  array{q: string, status: string, sort: string, perPage: int}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $query->whereRaw('('.self::SEARCH_EXPRESSION.") ILIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%',
            ]);
        }

        match ($filters['status']) {
            'active' => $query->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            default => null,
        };
    }

    /** @param Builder<ProductService> $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }

    /**
     * @param  Collection<string, CompanyCurrency>  $currencies
     * @param  Collection<string, TaxPreset>  $taxPresets
     * @return array<string, mixed>
     */
    private function row(
        Company $company,
        ProductService $product,
        Collection $currencies,
        Collection $taxPresets,
    ): array {
        $currency = $product->currency_id === null ? null : $currencies->get($product->currency_id);
        $taxPreset = $product->tax_preset_id === null ? null : $taxPresets->get($product->tax_preset_id);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'internalCode' => $product->internal_code,
            'unitPrice' => $product->unit_price === null || ! $currency instanceof CompanyCurrency
                ? null
                : (string) DecimalRules::moneySource($product->unit_price)
                    ->toScale(DecimalRules::currencyPrecision($currency->currency_precision)),
            'currencyId' => $product->currency_id,
            'currencyCode' => $currency?->currency_code,
            'unit' => $product->unit,
            'periodUnit' => $product->period_unit->value,
            'periodLabel' => __("catalog_ui.form.periods.{$product->period_unit->value}"),
            'taxPresetId' => $product->tax_preset_id,
            'taxPresetName' => $taxPreset?->name,
            'archived' => $product->archived_at !== null,
            'updatedAt' => $product->updated_at?->toISOString(),
            'updateUrl' => route('catalog.update', [$company, $product], false),
            'archiveUrl' => route('catalog.archive', [$company, $product], false),
            'restoreUrl' => route('catalog.restore', [$company, $product], false),
            'deleteUrl' => route('catalog.destroy', [$company, $product], false),
        ];
    }
}
