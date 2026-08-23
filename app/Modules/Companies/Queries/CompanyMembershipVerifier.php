<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Tenancy\Contracts\VerifiesTenantMembership;
use App\Models\User;
use App\Modules\Companies\Models\CompanyMembership;

final readonly class CompanyMembershipVerifier implements VerifiesTenantMembership
{
    public function allows(User $user, string $companyId): bool
    {
        return CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
