<?php

namespace App\Foundation\Tenancy\Contracts;

use App\Models\User;

interface VerifiesTenantMembership
{
    public function allows(User $user, string $companyId): bool;
}
