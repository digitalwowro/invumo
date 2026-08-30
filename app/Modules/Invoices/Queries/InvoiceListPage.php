<?php

namespace App\Modules\Invoices\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Data\ResolvedInvoiceState;
use App\Modules\Invoices\Http\Requests\InvoiceListRequest;
use App\Modules\Transactions\Queries\InvoiceLedgerAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class InvoiceListPage
{
    private const CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    private const SEARCH = "coalesce(documents.rendered_number, '') || ' ' || coalesce(documents.customer_reference, '') || ' ' || coalesce(customer.first_name, '') || ' ' || coalesce(customer.last_name, '') || ' ' || coalesce(customer.legal_name, '') || ' ' || coalesce(customer.email, '')";

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private InvoiceLedgerAggregate $ledger,
        private InvoiceListSummary $summary,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, InvoiceListRequest $request): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewInvoices)) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $canManage = $this->abilities->allows($actor, $company, CompanyAbility::ManageInvoices);
        $settings = CompanySetting::query()->firstOrFail();
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();
        $query = $this->query();
        $this->applyFilters($query, $filters, $localDate);
        $page = $this->applySort($query, $filters['sort'])
            ->cursorPaginate($filters['perPage'])
            ->withQueryString();

        return [
            'invoices' => [
                'items' => array_map(
                    fn (stdClass $row): array => $this->row($company, $row, $localDate, $canManage),
                    $page->items(),
                ),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'summary' => $this->summary->for($localDate),
            'datePresets' => [
                'today' => $localDate->toDateString(),
                'monthStart' => $localDate->startOfMonth()->toDateString(),
                'ninetyDaysAgo' => $localDate->subDays(89)->toDateString(),
                'nextThirtyDays' => $localDate->addDays(30)->toDateString(),
                'yesterday' => $localDate->subDay()->toDateString(),
            ],
            'indexUrl' => route('invoices.index', $company, false),
            'createUrl' => $canManage ? route('invoices.create', $company, false) : null,
        ];
    }

    private function query(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('documents')
            ->leftJoinSub($this->ledger->query(), 'ledger', function ($join): void {
                $join->on('ledger.company_id', '=', 'documents.company_id')
                    ->on('ledger.invoice_id', '=', 'documents.id');
            })
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
            ->selectRaw('COALESCE(ledger.net_paid, 0) AS net_paid')
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name')
            ->selectRaw('customer.email AS customer_email')
            ->selectRaw('lower('.self::CUSTOMER_NAME.') AS customer_sort_name')
            ->selectRaw("COALESCE(invoices.due_date, DATE '9999-12-31') AS due_sort_date");
    }

    /** @param array{q: string, issueFrom: string, issueTo: string, dueFrom: string, dueTo: string, lifecycle: string, payment: string, overdue: string, sort: string, perPage: int} $filters */
    private function applyFilters(Builder $query, array $filters, CarbonImmutable $localDate): void
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

        if ($filters['lifecycle'] !== 'all') {
            $query->where('invoices.lifecycle', $filters['lifecycle']);
        }

        if ($filters['payment'] === 'OUTSTANDING') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('documents.total > COALESCE(ledger.net_paid, 0)');
        } elseif ($filters['payment'] === 'PAID') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('documents.total = COALESCE(ledger.net_paid, 0)');
        } elseif ($filters['payment'] === 'PARTIALLY_PAID') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('COALESCE(ledger.net_paid, 0) > 0')
                ->whereRaw('COALESCE(ledger.net_paid, 0) < documents.total');
        } elseif ($filters['payment'] === 'UNPAID') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->where('documents.total', '>', '0')
                ->whereRaw('COALESCE(ledger.net_paid, 0) = 0');
        }

        if ($filters['overdue'] === 'overdue') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('documents.total > COALESCE(ledger.net_paid, 0)')
                ->where('invoices.due_date', '<', $localDate->toDateString());
        } elseif ($filters['overdue'] === 'due_soon') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('documents.total > COALESCE(ledger.net_paid, 0)')
                ->whereBetween('invoices.due_date', [
                    $localDate->toDateString(),
                    $localDate->addDays(7)->toDateString(),
                ]);
        } elseif ($filters['overdue'] === 'not_due') {
            $query->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('documents.total > COALESCE(ledger.net_paid, 0)')
                ->where('invoices.due_date', '>', $localDate->addDays(7)->toDateString());
        }
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        $sortable = DB::connection(config('database.tenant_connection'))
            ->query()
            ->fromSub($query, 'invoice_list');

        return match ($sort) {
            'issue_asc' => $sortable->orderBy('issue_sort_date')->orderBy('id'),
            'due_asc' => $sortable->orderBy('due_sort_date')->orderBy('id'),
            'total_desc' => $sortable->orderByDesc('total')->orderByDesc('id'),
            'total_asc' => $sortable->orderBy('total')->orderBy('id'),
            'customer_asc' => $sortable->orderBy('customer_sort_name')->orderBy('id'),
            'recent' => $sortable->orderByDesc('updated_at')->orderByDesc('id'),
            default => $sortable->orderByDesc('issue_sort_date')->orderByDesc('id'),
        };
    }

    /** @return array<string, mixed> */
    private function row(
        Company $company,
        stdClass $row,
        CarbonImmutable $localDate,
        bool $canManage,
    ): array {
        $precision = $row->currency_precision === null
            ? null
            : DecimalRules::currencyPrecision((int) $row->currency_precision);
        $lifecycle = InvoiceLifecycle::from((string) $row->lifecycle);
        $state = ResolvedInvoiceState::resolve(
            $lifecycle,
            (string) $row->total,
            (string) $row->net_paid,
            $row->due_date === null ? null : new CarbonImmutable((string) $row->due_date),
            $localDate,
        );

        return [
            'id' => (string) $row->id,
            'number' => (string) $row->rendered_number,
            'customerName' => $row->customer_name,
            'customerEmail' => $row->customer_email,
            'customerReference' => $row->customer_reference,
            'issueDate' => $row->issue_date,
            'dueDate' => $row->due_date,
            'lifecycle' => $lifecycle->value,
            'paymentState' => $state->paymentState?->value,
            'isOverdue' => $state->isOverdue,
            'displayStatus' => $state->displayStatus->value,
            'total' => $precision === null
                ? null
                : (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
            'outstanding' => $precision === null
                ? null
                : (string) DecimalRules::moneySource((string) $row->total)
                    ->minus(DecimalRules::moneySource((string) $row->net_paid))
                    ->toScale($precision),
            'currencyCode' => $row->currency_code,
            'editUrl' => $canManage ? route('invoices.edit', [$company, $row->id], false) : null,
            'viewUrl' => route('invoices.current.show', [$company, $row->id], false),
        ];
    }
}
