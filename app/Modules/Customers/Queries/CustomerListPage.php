<?php

namespace App\Modules\Customers\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Http\Requests\CustomerListRequest;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class CustomerListPage
{
    private const SEARCH_EXPRESSION = <<<'SQL'
        coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' ||
        coalesce(legal_name, '') || ' ' || coalesce(external_reference, '') || ' ' ||
        coalesce(email, '') || ' ' || coalesce(tax_registration_identifier, '') || ' ' ||
        coalesce(business_registration_number, '')
        SQL;

    private const DISPLAY_NAME_EXPRESSION = <<<'SQL'
        CASE
            WHEN type = 'COMPANY' THEN legal_name
            ELSE first_name || ' ' || last_name
        END
        SQL;

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerFormOptions $options,
        private CustomerListSummary $summary,
    ) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        CustomerListRequest $request,
        string $locale,
    ): array {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewCustomers)) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $query = Customer::query();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();

        return [
            'customers' => [
                'items' => array_values(array_map(
                    fn (Customer $customer): array => $this->row($company, $customer),
                    $page->items(),
                )),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'summary' => $this->summary->get(),
            'abilities' => [
                'create' => $this->abilities->allows($actor, $company, CompanyAbility::ManageCustomers),
                'delete' => $this->abilities->allows($actor, $company, CompanyAbility::DeleteCustomers),
            ],
            'indexUrl' => route('customers.index', $company, false),
            'createUrl' => route('customers.create', $company, false),
            ...$this->options->for($locale),
        ];
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array{q: string, status: string, country: ?string, sort: string, perPage: int}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $query->whereRaw(
                '('.self::SEARCH_EXPRESSION.") ILIKE ? ESCAPE '!'",
                [$this->literalSearchPattern($filters['q'])],
            );
        }

        match ($filters['status']) {
            'active' => $query->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            default => null,
        };

        if ($filters['country'] !== null) {
            $query->where('country_code', $filters['country']);
        }
    }

    /** @param Builder<Customer> $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_asc' => $this->applyNameSort($query, false),
            'name_desc' => $this->applyNameSort($query, true),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }

    /** @param Builder<Customer> $query */
    private function applyNameSort(Builder $query, bool $descending): void
    {
        $source = Customer::query()
            ->select('customers.*')
            ->selectRaw(self::DISPLAY_NAME_EXPRESSION.' AS display_sort_name');

        $query->fromSub($source, 'customers');

        if ($descending) {
            $query->orderByDesc('display_sort_name')->orderByDesc('id');

            return;
        }

        $query->orderBy('display_sort_name')->orderBy('id');
    }

    private function literalSearchPattern(string $search): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
    }

    /** @return array<string, mixed> */
    private function row(Company $company, Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'displayName' => $customer->displayName(),
            'type' => $customer->type->value,
            'typeLabel' => __("customers_ui.form.types.{$customer->type->value}"),
            'email' => $customer->email,
            'externalReference' => $customer->external_reference,
            'countryCode' => $customer->country_code,
            'archived' => $customer->archived_at !== null,
            'updatedAt' => $customer->updated_at?->toISOString(),
            'workspaceUrl' => route('customers.show', [$company, $customer], false),
        ];
    }
}
