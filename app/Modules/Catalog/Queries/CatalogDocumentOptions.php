<?php

namespace App\Modules\Catalog\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CatalogDocumentOptions
{
    private const SEARCH_EXPRESSION = "coalesce(name, '') || ' ' || coalesce(internal_code, '') || ' ' || coalesce(description, '')";

    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return list<array{id: string, name: string, internalCode: string|null, defaultsUrl: string}> */
    public function search(Company $company, User $actor, string $search): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCatalog)) {
            throw new AuthorizationException;
        }

        $query = ProductService::query()->whereNull('archived_at');

        if ($search !== '') {
            $query->whereRaw('('.self::SEARCH_EXPRESSION.") ILIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%',
            ]);
        }

        return array_values($query->orderBy('name')->orderBy('id')->limit(20)->get()
            ->map(fn (ProductService $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'internalCode' => $product->internal_code,
                'defaultsUrl' => route('quote-sources.products.show', [$company, $product], false),
            ])->all());
    }
}
