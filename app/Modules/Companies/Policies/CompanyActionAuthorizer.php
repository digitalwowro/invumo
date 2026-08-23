<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyActionAuthorizer
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /**
     * Re-read and lock the membership inside the mutation transaction so a
     * removed or downgraded session cannot retain its former authority.
     *
     * @throws AuthorizationException
     */
    public function authorize(User $actor, Company $company, CompanyAbility $ability): CompanyMembership
    {
        $activeCompany = Company::query()
            ->whereKey($company->id)
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->first();

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->lockForUpdate()
            ->first();

        if (
            $activeCompany === null
            || $membership === null
            || ! $this->authorization->allows($membership->role, $ability)
        ) {
            throw new AuthorizationException;
        }

        return $membership;
    }
}
