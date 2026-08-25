<?php

namespace App\Modules\Companies\Contracts;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;

interface AuthorizesCompanyActions
{
    public function authorize(
        User $actor,
        Company $company,
        CompanyAbility $ability,
    ): CompanyMembership;
}
