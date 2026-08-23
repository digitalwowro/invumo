<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Exceptions\CompanyOwnershipTransferException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyOwnershipTransferAuthorizer
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /**
     * @return array{company: Company, owner: CompanyMembership, destination: CompanyMembership, destinationAccount: Account}
     */
    public function authorize(User $actor, Company $company, CompanyMembership $destination): array
    {
        $activeCompany = Company::query()
            ->whereKey($company->id)
            ->whereNull('archived_at')
            ->lockForUpdate()
            ->first();

        if ($activeCompany === null) {
            throw new AuthorizationException;
        }

        $accounts = Account::query()
            ->whereIn('owner_user_id', [$actor->id, $destination->user_id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $memberships = CompanyMembership::query()
            ->where('company_id', $activeCompany->id)
            ->where(function ($query) use ($actor, $destination): void {
                $query->where('user_id', $actor->id)->orWhere('id', $destination->id);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $owner = $memberships->firstWhere('user_id', $actor->id);
        $target = $memberships->firstWhere('id', $destination->id);
        $currentAccount = $accounts->firstWhere('owner_user_id', $actor->id);

        if (
            $owner === null
            || $currentAccount === null
            || $activeCompany->owning_account_id !== $currentAccount->id
            || ! $this->authorization->allows($owner->role, CompanyAbility::TransferOwnership)
        ) {
            throw new AuthorizationException;
        }

        if ($target === null || $target->id === $owner->id || $target->role === CompanyRole::Owner) {
            throw CompanyOwnershipTransferException::memberUnavailable();
        }

        $destinationAccount = $accounts->firstWhere('owner_user_id', $target->user_id);

        if ($destinationAccount === null) {
            throw CompanyOwnershipTransferException::destinationAccountUnavailable();
        }

        if (! Plan::query()->whereKey($destinationAccount->plan_id)->where('active', true)->exists()) {
            throw CompanyOwnershipTransferException::destinationPlanUnavailable();
        }

        return [
            'company' => $activeCompany,
            'owner' => $owner,
            'destination' => $target,
            'destinationAccount' => $destinationAccount,
        ];
    }
}
