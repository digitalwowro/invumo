<?php

namespace App\Modules\Invoices\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Invoices\Http\Requests\InvoiceListRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class InvoiceListPage
{
    private const CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    private const SEARCH = "coalesce(documents.rendered_number, '') || ' ' || coalesce(documents.customer_reference, '') || ' ' || coalesce(customer.first_name, '') || ' ' || coalesce(customer.last_name, '') || ' ' || coalesce(customer.legal_name, '')";

    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, InvoiceListRequest $request): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewInvoices)) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $query = $this->query();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();

        return [
            'invoices' => [
                'items' => array_map(
                    fn (stdClass $row): array => $this->row($company, $row),
                    $page->items(),
                ),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'indexUrl' => route('invoices.index', $company, false),
            'createUrl' => route('invoices.create', $company, false),
        ];
    }

    private function query(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('documents')
            ->join('invoices', function ($join): void {
                $join->on('invoices.company_id', '=', 'documents.company_id')
                    ->on('invoices.document_id', '=', 'documents.id');
            })
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->where('documents.kind', 'INVOICE')
            ->select([
                'documents.id', 'documents.rendered_number', 'documents.customer_reference',
                'documents.issue_date', 'documents.issue_sort_date',
                'documents.currency_code', 'documents.currency_precision',
                'documents.total', 'documents.updated_at', 'invoices.lifecycle', 'invoices.due_date',
            ])
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name');
    }

    /** @param array{q: string, issueFrom: string, issueTo: string, dueFrom: string, dueTo: string, sort: string, perPage: int} $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $query->whereRaw('('.self::SEARCH.") ILIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%',
            ]);
        }

        foreach ([
            ['documents.issue_date', '>=', $filters['issueFrom']],
            ['documents.issue_date', '<=', $filters['issueTo']],
            ['invoices.due_date', '>=', $filters['dueFrom']],
            ['invoices.due_date', '<=', $filters['dueTo']],
        ] as [$column, $operator, $value]) {
            if ($value !== '') {
                $query->where($column, $operator, $value);
            }
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'issue_asc' => $query->orderBy('documents.issue_sort_date')->orderBy('documents.id'),
            'recent' => $query->orderByDesc('documents.updated_at')->orderByDesc('documents.id'),
            default => $query->orderByDesc('documents.issue_sort_date')->orderByDesc('documents.id'),
        };
    }

    /** @return array<string, mixed> */
    private function row(Company $company, stdClass $row): array
    {
        $precision = $row->currency_precision === null
            ? null
            : DecimalRules::currencyPrecision((int) $row->currency_precision);

        return [
            'id' => (string) $row->id,
            'number' => (string) $row->rendered_number,
            'customerName' => $row->customer_name,
            'customerReference' => $row->customer_reference,
            'issueDate' => $row->issue_date,
            'dueDate' => $row->due_date,
            'lifecycle' => (string) $row->lifecycle,
            'total' => $precision === null
                ? null
                : (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
            'currencyCode' => $row->currency_code,
            'editUrl' => route('invoices.edit', [$company, $row->id], false),
            'viewUrl' => route('invoices.current.show', [$company, $row->id], false),
        ];
    }
}
