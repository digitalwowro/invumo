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

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private CustomerFormOptions $options,
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
            $query->whereRaw('('.self::SEARCH_EXPRESSION.') ILIKE ?', ['%'.$filters['q'].'%']);
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
        $name = "CASE WHEN type = 'COMPANY' THEN legal_name ELSE first_name || ' ' || last_name END";

        match ($sort) {
            'name_asc' => $query
                ->select('customers.*')
                ->selectRaw("{$name} AS display_sort_name")
                ->orderBy('display_sort_name')
                ->orderBy('id'),
            'name_desc' => $query
                ->select('customers.*')
                ->selectRaw("{$name} AS display_sort_name")
                ->orderByDesc('display_sort_name')
                ->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
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
