<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Models\CompanyMembership;
use Illuminate\Database\Eloquent\Collection;

final readonly class AccessibleCompanies
{
    /**
     * @return Collection<int, CompanyMembership>
     */
    public function for(User $user): Collection
    {
        return CompanyMembership::query()
            ->select('company_memberships.*')
            ->join('companies', 'companies.id', '=', 'company_memberships.company_id')
            ->with('company:id,name')
            ->where('company_memberships.user_id', $user->id)
            ->whereNull('companies.archived_at')
            ->orderBy('companies.name')
            ->get();
    }
}
