<?php

namespace App\Modules\Recurring\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Recurring\Data\RecurringRunOutcome;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Http\Requests\RecurringTemplateListRequest;
use App\Modules\Recurring\Models\RecurringOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class RecurringTemplateListPage
{
    private const CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    private const SEARCH = "recurring_templates.internal_name || ' ' || coalesce(recurring_templates.customer_reference, '') || ' ' || coalesce(customer.first_name, '') || ' ' || coalesce(customer.last_name, '') || ' ' || coalesce(customer.legal_name, '')";

    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        RecurringTemplateListRequest $request,
    ): array {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewRecurringTemplates)) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $query = $this->query();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();
        $canDelete = $this->abilities->allows(
            $actor,
            $company,
            CompanyAbility::DeleteRecurringTemplates,
        );
        $occurrences = RecurringOccurrence::query()
            ->whereIn('recurring_template_id', array_column($page->items(), 'id'))
            ->orderBy('recurring_template_id')
            ->orderByDesc('logical_ordinal')
            ->get()
            ->unique('recurring_template_id')
            ->keyBy('recurring_template_id');

        return [
            'templates' => [
                'items' => array_map(
                    fn (stdClass $row): array => $this->row(
                        $company,
                        $row,
                        $canDelete,
                        $occurrences->get((string) $row->id),
                    ),
                    $page->items(),
                ),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'indexUrl' => route('recurring.index', $company, false),
            'createUrl' => route('recurring.create', $company, false),
        ];
    }

    private function query(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('recurring_templates')
            ->join('customers as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'recurring_templates.company_id')
                    ->on('customer.id', '=', 'recurring_templates.customer_id');
            })
            ->select([
                'recurring_templates.id', 'recurring_templates.internal_name',
                'recurring_templates.customer_reference', 'recurring_templates.state',
                'recurring_templates.next_run_at', 'recurring_templates.updated_at',
                'recurring_templates.last_run_outcome',
                'recurring_templates.automatic_email_enabled',
                'recurring_templates.currency_review_required',
            ])
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name');
    }

    /** @param array{q: string, sort: string, outcome: string, perPage: int} $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $query->whereRaw('('.self::SEARCH.") ILIKE ? ESCAPE '!'", ["%{$escaped}%"]);
        }

        if ($filters['outcome'] === 'failed') {
            $query->where('recurring_templates.state', RecurringTemplateState::Active)
                ->where('recurring_templates.last_run_outcome', RecurringRunOutcome::Failed);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_asc' => $query->orderBy('recurring_templates.internal_name')
                ->orderBy('recurring_templates.id'),
            'name_desc' => $query->orderByDesc('recurring_templates.internal_name')
                ->orderByDesc('recurring_templates.id'),
            default => $query->orderByDesc('recurring_templates.updated_at')
                ->orderByDesc('recurring_templates.id'),
        };
    }

    /** @return array<string, mixed> */
    private function row(
        Company $company,
        stdClass $row,
        bool $canDelete,
        ?RecurringOccurrence $occurrence,
    ): array {
        $state = RecurringTemplateState::from((string) $row->state);

        return [
            'id' => (string) $row->id,
            'internalName' => (string) $row->internal_name,
            'customerName' => (string) $row->customer_name,
            'customerReference' => $row->customer_reference,
            'state' => $state->value,
            'nextRunAt' => $row->next_run_at === null
                ? null : CarbonImmutable::parse((string) $row->next_run_at)->toISOString(),
            'lastRunOutcome' => $row->last_run_outcome,
            'automaticEmailEnabled' => (bool) $row->automatic_email_enabled,
            'currencyReviewRequired' => (bool) $row->currency_review_required,
            'lastInvoiceUrl' => $occurrence === null
                ? null : route('invoices.edit', [$company, $occurrence->invoice_id], false),
            'updatedAt' => CarbonImmutable::parse((string) $row->updated_at)->toISOString(),
            'editUrl' => route('recurring.edit', [$company, $row->id], false),
            'deleteUrl' => route('recurring.destroy', [$company, $row->id], false),
            'canDelete' => $canDelete && $state === RecurringTemplateState::Draft,
        ];
    }
}
