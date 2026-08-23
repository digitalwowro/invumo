<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Exceptions\CompanyMembershipException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyMembershipActionAuthorizer
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /**
     * Lock both memberships in stable identifier order so two simultaneous
     * membership changes cannot deadlock by locking actor and target inversely.
     *
     * @return array{actor: CompanyMembership, target: CompanyMembership}
     */
    public function target(User $actor, Company $company, CompanyMembership $target): array
    {
        $this->lockActiveCompany($company);

        $memberships = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($actor, $target): void {
                $query->where('user_id', $actor->id)->orWhere('id', $target->id);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $actorMembership = $memberships->firstWhere('user_id', $actor->id);
        $targetMembership = $memberships->firstWhere('id', $target->id);

        if (
            $actorMembership === null
            || $targetMembership === null
            || ! $this->authorization->allows($actorMembership->role, CompanyAbility::ManageMembers)
        ) {
            throw new AuthorizationException;
        }

        if ($actorMembership->id === $targetMembership->id) {
            throw CompanyMembershipException::cannotManageSelf();
        }

        if ($targetMembership->role === CompanyRole::Owner) {
            throw CompanyMembershipException::ownerRequiresTransfer();
        }

        return ['actor' => $actorMembership, 'target' => $targetMembership];
    }

    public function self(User $actor, Company $company): CompanyMembership
    {
        $this->lockActiveCompany($company);

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            throw new AuthorizationException;
        }

        if ($membership->role === CompanyRole::Owner) {
            throw CompanyMembershipException::ownerRequiresTransfer();
        }

        return $membership;
    }

    private function lockActiveCompany(Company $company): void
    {
        $activeCompany = Company::query()
            ->whereKey($company->id)
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->first();

        if ($activeCompany === null) {
            throw new AuthorizationException;
        }
    }
}
