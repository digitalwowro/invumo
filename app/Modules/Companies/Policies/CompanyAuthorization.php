<?php

namespace App\Modules\Companies\Policies;

use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;

final readonly class CompanyAuthorization
{
    public function allows(CompanyRole $role, CompanyAbility $ability): bool
    {
        return match ($role) {
            CompanyRole::Owner => true,
            CompanyRole::Admin => ! in_array($ability, [
                CompanyAbility::ManageAccount,
                CompanyAbility::TransferOwnership,
                CompanyAbility::DeleteCompany,
            ], true),
            CompanyRole::Member => $ability === CompanyAbility::ViewCompany,
        };
    }

    /**
     * @return array<string, bool>
     */
    public function bagFor(CompanyRole $role): array
    {
        $abilities = [];

        foreach (CompanyAbility::cases() as $ability) {
            $abilities[$ability->value] = $this->allows($role, $ability);
        }

        return $abilities;
    }
}
