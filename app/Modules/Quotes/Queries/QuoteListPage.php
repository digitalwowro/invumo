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

    public function __construct(private CompanyAbilityCheck $abilities) {}

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
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();
        $canDelete = $this->abilities->allows($actor, $company, CompanyAbility::DeleteQuotes);

        return [
            'quotes' => [
                'items' => array_map(
                    fn (stdClass $row): array => $this->row($company, $row, $localDate, $canDelete),
                    $page->items(),
                ),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
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
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name');
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

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'issue_asc' => $query->orderBy('documents.issue_sort_date')->orderBy('documents.id'),
            'recent' => $query->orderByDesc('documents.updated_at')->orderByDesc('documents.id'),
            default => $query->orderByDesc('documents.issue_sort_date')->orderByDesc('documents.id'),
        };
    }

    /** @return array<string, mixed> */
    private function row(Company $company, stdClass $row, CarbonImmutable $localDate, bool $canDelete): array
    {
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
            'deleteUrl' => route('quotes.destroy', [$company, $row->id], false),
            'deleteHighRisk' => $lifecycle !== QuoteLifecycle::Draft,
            'canDelete' => $canDelete,
        ];
    }
}
