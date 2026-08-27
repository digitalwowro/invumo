<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;

final readonly class CompanyAbilityCheck
{
    public function __construct(private CompanyAuthorization $authorization) {}

    public function allows(User $actor, Company $company, CompanyAbility $ability): bool
    {
        return $this->allowsAll($actor, $company, $ability);
    }

    public function allowsAll(
        User $actor,
        Company $company,
        CompanyAbility $ability,
        CompanyAbility ...$additionalAbilities,
    ): bool {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($membership === null) {
            return false;
        }

        foreach ([$ability, ...$additionalAbilities] as $requiredAbility) {
            if (! $this->authorization->allows($membership->role, $requiredAbility)) {
                return false;
            }
        }

        return true;
    }
}
