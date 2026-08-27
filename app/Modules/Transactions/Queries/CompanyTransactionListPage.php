<?php

namespace App\Modules\Transactions\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Transactions\Http\Requests\CompanyTransactionListRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyTransactionListPage
{
    private const SEARCH = "coalesce(documents.rendered_number, '') || ' ' || coalesce(customer.legal_name, '') || ' ' || coalesce(customer.first_name, '') || ' ' || coalesce(customer.last_name, '') || ' ' || coalesce(entry.reference, '') || ' ' || coalesce(entry.payment_method, '')";

    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        CompanyTransactionListRequest $request,
    ): array {
        if (! $this->abilities->allowsAll(
            $actor,
            $company,
            CompanyAbility::ViewTransactions,
            CompanyAbility::ViewInvoices,
        )) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $query = $this->query();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();

        return [
            'transactions' => [
                'items' => array_map(
                    fn (stdClass $row): array => $this->row($company, $row),
                    $page->items(),
                ),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'indexUrl' => route('transactions.index', $company, false),
        ];
    }

    private function query(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('invoice_transactions as entry')
            ->join('documents', function ($join): void {
                $join->on('documents.company_id', '=', 'entry.company_id')
                    ->on('documents.id', '=', 'entry.invoice_id');
            })
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->select([
                'entry.id', 'entry.kind', 'entry.adjustment_direction',
                'entry.amount', 'entry.currency_code',
                'entry.currency_precision', 'entry.transaction_date',
                'entry.payment_method', 'entry.reference',
                'entry.created_at', 'documents.id as invoice_id',
                'documents.rendered_number as invoice_number',
            ])
            ->selectRaw(<<<'SQL'
                CASE WHEN customer.type = 'COMPANY'
                    THEN customer.legal_name
                    ELSE concat_ws(' ', customer.first_name, customer.last_name)
                END AS customer_name
                SQL);
    }

    /** @param array{q: string, dateFrom: string, dateTo: string, kind: string, sort: string, perPage: int} $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $query->whereRaw('('.self::SEARCH.") ILIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%',
            ]);
        }

        if ($filters['dateFrom'] !== '') {
            $query->where('entry.transaction_date', '>=', $filters['dateFrom']);
        }

        if ($filters['dateTo'] !== '') {
            $query->where('entry.transaction_date', '<=', $filters['dateTo']);
        }

        if ($filters['kind'] !== 'all') {
            $query->where('entry.kind', $filters['kind']);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'date_asc' => $query->orderBy('entry.transaction_date')->orderBy('entry.id'),
            'recent' => $query->orderByDesc('entry.created_at')->orderByDesc('entry.id'),
            default => $query->orderByDesc('entry.transaction_date')->orderByDesc('entry.id'),
        };
    }

    /** @return array<string, mixed> */
    private function row(Company $company, stdClass $row): array
    {
        return [
            'id' => (string) $row->id,
            'kind' => (string) $row->kind,
            'adjustmentDirection' => $row->adjustment_direction,
            'amount' => (string) DecimalRules::storedMoney(
                (string) $row->amount,
                DecimalRules::currencyPrecision((int) $row->currency_precision),
            ),
            'currencyCode' => (string) $row->currency_code,
            'transactionDate' => (string) $row->transaction_date,
            'paymentMethod' => $row->payment_method,
            'reference' => $row->reference,
            'invoiceNumber' => (string) $row->invoice_number,
            'customerName' => $row->customer_name,
            'invoiceUrl' => route('invoices.edit', [$company, $row->invoice_id], false),
        ];
    }
}
