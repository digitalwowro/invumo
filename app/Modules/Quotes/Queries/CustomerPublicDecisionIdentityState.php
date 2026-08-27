<?php

namespace App\Modules\Quotes\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Quotes\Models\QuotePublicDecision;

final readonly class CustomerPublicDecisionIdentityState
{
    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array{count: int, eraseUrl: string|null} */
    public function for(Company $company, User $actor, Customer $customer): array
    {
        $count = QuotePublicDecision::query()
            ->where('customer_id', $customer->id)
            ->whereNull('identity_redacted_at')
            ->count();

        return [
            'count' => $count,
            'eraseUrl' => $count > 0 && $this->abilities->allows(
                $actor,
                $company,
                CompanyAbility::DeleteCustomers,
            )
                ? route('customer-public-decision-identity.destroy', [$company, $customer], false)
                : null,
        ];
    }
}
