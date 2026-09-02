<?php

namespace App\Modules\Catalog\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ProductServiceCreatePage
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CatalogFormOptions $options,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ManageCatalog)) {
            throw new AuthorizationException;
        }

        return [
            'indexUrl' => route('catalog.index', $company, false),
            'storeUrl' => route('catalog.store', $company, false),
            ...$this->options->for(),
        ];
    }
}
