<?php

namespace App\Modules\Customers\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CustomerDocumentOptions
{
    private const SEARCH_EXPRESSION = <<<'SQL'
        coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' ||
        coalesce(legal_name, '') || ' ' || coalesce(external_reference, '') || ' ' ||
        coalesce(email, '')
        SQL;

    private const DISPLAY_NAME_EXPRESSION = "CASE WHEN type = 'COMPANY' THEN legal_name ELSE first_name || ' ' || last_name END";

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private ResolveDocumentCustomer $resolver,
    ) {}

    /** @return list<array{id: string, displayName: string, email: string|null, externalReference: string|null, previewUrl: string}> */
    public function search(Company $company, User $actor, string $search): array
    {
        $this->authorize($company, $actor);
        $query = Customer::query()->whereNull('archived_at');

        if ($search !== '') {
            $query->whereRaw('('.self::SEARCH_EXPRESSION.") ILIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%',
            ]);
        }

        return array_values($query->orderByRaw(self::DISPLAY_NAME_EXPRESSION)
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'displayName' => $customer->displayName(),
                'email' => $customer->email,
                'externalReference' => $customer->external_reference,
                'previewUrl' => route('quote-sources.customers.show', [$company, $customer], false),
            ])->all());
    }

    /** @return array<string, mixed> */
    public function preview(Company $company, User $actor, ?string $customerId): array
    {
        return $this->resolved($company, $actor, $customerId)->preview();
    }

    public function resolved(
        Company $company,
        User $actor,
        ?string $customerId,
    ): ResolvedDocumentCustomer {
        $this->authorize($company, $actor);

        return $this->resolver->for($customerId);
    }

    private function authorize(Company $company, User $actor): void
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCustomers)) {
            throw new AuthorizationException;
        }
    }
}
