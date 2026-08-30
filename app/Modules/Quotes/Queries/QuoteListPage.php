<?php

namespace App\Modules\Quotes\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Quotes\Data\QuoteDisplayStatus;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Http\Requests\QuoteListRequest;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class QuoteListPage
{
    private const CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    private const SEARCH = "coalesce(documents.rendered_number, '') || ' ' || coalesce(documents.customer_reference, '') || ' ' || coalesce(customer.first_name, '') || ' ' || coalesce(customer.last_name, '') || ' ' || coalesce(customer.legal_name, '')";

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private QuoteDeletionPreview $deletionPreview,
        private QuoteListSummary $summary,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor, QuoteListRequest $request): array
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewQuotes)) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();
        $filters = $request->filters();
        $query = $this->query();
        $this->applyFilters($query, $filters, $localDate->toDateString());
        $page = $this->applySort($query, $filters['sort'])
            ->cursorPaginate($filters['perPage'])
            ->withQueryString();
        $canDelete = $this->abilities->allows($actor, $company, CompanyAbility::DeleteQuotes);
        $lifecycles = [];

        foreach ($page->items() as $row) {
            $lifecycles[(string) $row->id] = QuoteLifecycle::from((string) $row->lifecycle);
        }

        $deletions = $canDelete
            ? $this->deletionPreview->forDocuments($lifecycles)
            : array_map(fn (): array => [
                'highRisk' => false,
                'guard' => ['blocked' => false, 'description' => null],
            ], $lifecycles);

        return [
            'quotes' => [
                'items' => array_map(
                    fn (stdClass $row): array => $this->row(
                        $company, $row, $localDate, $canDelete, $deletions[(string) $row->id],
                    ),
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
            'indexUrl' => route('quotes.index', $company, false),
            'createUrl' => route('quotes.create', $company, false),
            'abilities' => ['delete' => $canDelete],
        ];
    }

    private function query(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('documents')
            ->join('quotes', function ($join): void {
                $join->on('quotes.company_id', '=', 'documents.company_id')
                    ->on('quotes.document_id', '=', 'documents.id');
            })
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->where('documents.kind', 'QUOTE')
            ->select([
                'documents.id', 'documents.rendered_number', 'documents.customer_reference',
                'documents.issue_date', 'documents.issue_sort_date',
                'documents.currency_code', 'documents.currency_precision',
                'documents.total', 'documents.updated_at', 'quotes.lifecycle', 'quotes.valid_until',
            ])
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name')
            ->selectRaw('customer.email AS customer_email')
            ->selectRaw('lower('.self::CUSTOMER_NAME.') AS customer_sort_name')
            ->selectRaw("COALESCE(quotes.valid_until, DATE '9999-12-31') AS deadline_sort_date");
    }

    /** @param array{q: string, status: string, issueFrom: string, issueTo: string, validFrom: string, validTo: string, sort: string, perPage: int} $filters */
    private function applyFilters(Builder $query, array $filters, string $localDate): void
    {
        if ($filters['q'] !== '') {
            $query->whereRaw('('.self::SEARCH.") ILIKE ? ESCAPE '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%',
            ]);
        }

        $expired = "quotes.valid_until IS NOT NULL AND quotes.valid_until < ?::date AND quotes.lifecycle NOT IN ('ACCEPTED', 'REJECTED')";
        match ($filters['status']) {
            'EXPIRED' => $query->whereRaw($expired, [$localDate]),
            'DRAFT', 'SENT' => $query->where('quotes.lifecycle', $filters['status'])
                ->whereRaw('NOT ('.$expired.')', [$localDate]),
            'ACCEPTED', 'REJECTED' => $query->where('quotes.lifecycle', $filters['status']),
            default => null,
        };

        foreach ([
            ['documents.issue_date', '>=', $filters['issueFrom']],
            ['documents.issue_date', '<=', $filters['issueTo']],
            ['quotes.valid_until', '>=', $filters['validFrom']],
            ['quotes.valid_until', '<=', $filters['validTo']],
        ] as [$column, $operator, $value]) {
            if ($value !== '') {
                $query->where($column, $operator, $value);
            }
        }
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        $sortable = DB::connection(config('database.tenant_connection'))
            ->query()
            ->fromSub($query, 'quote_list');

        return match ($sort) {
            'issue_asc' => $sortable->orderBy('issue_sort_date')->orderBy('id'),
            'deadline_asc' => $sortable->orderBy('deadline_sort_date')->orderBy('id'),
            'total_desc' => $sortable->orderByDesc('total')->orderByDesc('id'),
            'total_asc' => $sortable->orderBy('total')->orderBy('id'),
            'customer_asc' => $sortable->orderBy('customer_sort_name')->orderBy('id'),
            'recent' => $sortable->orderByDesc('updated_at')->orderByDesc('id'),
            default => $sortable->orderByDesc('issue_sort_date')->orderByDesc('id'),
        };
    }

    /**
     * @param  array{highRisk: bool, guard: array{blocked: bool, description: string|null}}  $deletion
     * @return array<string, mixed>
     */
    private function row(
        Company $company,
        stdClass $row,
        CarbonImmutable $localDate,
        bool $canDelete,
        array $deletion,
    ): array {
        $lifecycle = QuoteLifecycle::from((string) $row->lifecycle);
        $validUntil = $row->valid_until === null
            ? null
            : CarbonImmutable::parse((string) $row->valid_until, 'UTC')->startOfDay();
        $status = QuoteDisplayStatus::resolve($lifecycle, $validUntil, $localDate);
        $precision = $row->currency_precision === null
            ? null
            : DecimalRules::currencyPrecision((int) $row->currency_precision);

        return [
            'id' => (string) $row->id,
            'number' => (string) $row->rendered_number,
            'customerName' => $row->customer_name,
            'customerEmail' => $row->customer_email,
            'customerReference' => $row->customer_reference,
            'issueDate' => $row->issue_date,
            'validUntil' => $row->valid_until,
            'lifecycle' => $lifecycle->value,
            'status' => $status->value,
            'total' => $precision === null
                ? null
                : (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
            'currencyCode' => $row->currency_code,
            'editUrl' => route('quotes.edit', [$company, $row->id], false),
            'viewUrl' => route('quotes.current.show', [$company, $row->id], false),
            'deleteUrl' => route('quotes.destroy', [$company, $row->id], false),
            'deletion' => $deletion,
            'canDelete' => $canDelete,
        ];
    }
}
