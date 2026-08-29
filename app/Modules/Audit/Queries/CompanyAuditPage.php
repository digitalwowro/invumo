<?php

namespace App\Modules\Audit\Queries;

use App\Models\User;
use App\Modules\Audit\Http\Requests\CompanyAuditListRequest;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyAuditPage
{
    private const EVENT_SEARCH = "coalesce(event.action, '') || ' ' || coalesce(event.target_type, '') || ' ' || coalesce(event.target_id::text, '') || ' ' || coalesce(event.actor_reference, '') || ' ' || coalesce(event.reason, '')";

    public function __construct(private CompanyAbilityCheck $abilities) {}

    /** @return array<string, mixed> */
    public function for(
        Company $company,
        User $actor,
        CompanyAuditListRequest $request,
    ): array {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewAudit)) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $timezone = CompanySetting::query()->where('company_id', $company->id)
            ->value('timezone') ?? 'UTC';
        $query = $this->query($company);
        $this->applyFilters($query, $filters, $timezone);
        $this->applySort($query, $filters['sort']);
        $page = $query->cursorPaginate($filters['perPage'])->withQueryString();

        return [
            'audit' => [
                'items' => array_map($this->row(...), $page->items()),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'filters' => $filters,
            'targetOptions' => $this->targetOptions($company),
            'timezone' => $timezone,
            'indexUrl' => route('company-audit.index', $company, false),
        ];
    }

    private function query(Company $company): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('audit_events as event')
            ->leftJoin('users as actor', 'actor.id', '=', 'event.actor_user_id')
            ->leftJoin('users as impersonator', 'impersonator.id', '=', 'event.impersonator_user_id')
            ->where('event.company_id', $company->id)
            ->select([
                'event.id', 'event.actor_type', 'event.actor_reference',
                'event.action', 'event.target_type', 'event.target_id',
                'event.occurred_at', 'event.reason', 'event.before', 'event.after',
                'actor.name as actor_name', 'impersonator.name as impersonator_name',
            ]);
    }

    /** @param array{q: string, dateFrom: string, dateTo: string, actorType: string, targetType: string, sort: string, perPage: int} $filters */
    private function applyFilters(Builder $query, array $filters, string $timezone): void
    {
        if ($filters['q'] !== '') {
            $pattern = '%'.$this->escapeLike($filters['q']).'%';
            $query->where(function (Builder $search) use ($pattern): void {
                $search->whereRaw('('.self::EVENT_SEARCH.") ILIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("coalesce(actor.name, '') ILIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("coalesce(impersonator.name, '') ILIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if ($filters['dateFrom'] !== '') {
            $query->where('event.occurred_at', '>=', $this->localDay($filters['dateFrom'], $timezone));
        }

        if ($filters['dateTo'] !== '') {
            $query->where('event.occurred_at', '<', $this->localDay($filters['dateTo'], $timezone)->addDay());
        }

        if ($filters['actorType'] !== 'all') {
            $query->where('event.actor_type', $filters['actorType']);
        }

        if ($filters['targetType'] !== 'all') {
            $query->where('event.target_type', $filters['targetType']);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'oldest') {
            $query->orderBy('event.occurred_at')->orderBy('event.id');

            return;
        }

        $query->orderByDesc('event.occurred_at')->orderByDesc('event.id');
    }

    /** @return array<string, mixed> */
    private function row(stdClass $row): array
    {
        return [
            'id' => (string) $row->id,
            'actorType' => (string) $row->actor_type,
            'actorName' => $row->actor_name,
            'actorReference' => $row->actor_reference,
            'impersonatorName' => $row->impersonator_name,
            'action' => (string) $row->action,
            'targetType' => (string) $row->target_type,
            'targetId' => (string) $row->target_id,
            'occurredAt' => (string) $row->occurred_at,
            'reason' => $row->reason,
            'before' => $this->json($row->before),
            'after' => $this->json($row->after),
        ];
    }

    /** @return list<string> */
    private function targetOptions(Company $company): array
    {
        $targets = DB::connection(config('database.tenant_connection'))
            ->table('audit_events')
            ->where('company_id', $company->id)
            ->distinct()
            ->orderBy('target_type')
            ->limit(50)
            ->pluck('target_type')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        return array_values($targets);
    }

    private function localDay(string $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)
            ->startOfDay()
            ->utc();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /** @return array<string, mixed>|null */
    private function json(mixed $value): ?array
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }
}
