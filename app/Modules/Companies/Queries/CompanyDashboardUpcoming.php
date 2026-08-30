<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Companies\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyDashboardUpcoming
{
    private const int LIMIT = 4;

    private const string CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    /** @return array<string, array{count: int, items: list<array<string, mixed>>}> */
    public function for(
        Company $company,
        CarbonImmutable $localDate,
        bool $includeQuotes,
        bool $includeRecurring,
    ): array {
        $groups = [];
        $sources = array_filter([
            $includeQuotes ? $this->quotes($company, $localDate) : null,
            $includeRecurring ? $this->recurring($company, $localDate) : null,
        ]);

        foreach ($sources as $source) {
            foreach ($source['counts'] as $currency => $count) {
                $group = $groups[$currency] ?? ['count' => 0, 'items' => []];
                $group['count'] += $count;
                $groups[$currency] = $group;
            }

            foreach ($source['items'] as $item) {
                $currency = (string) $item['currencyCode'];
                $group = $groups[$currency] ?? ['count' => 0, 'items' => []];
                $group['items'][] = $item;
                $groups[$currency] = $group;
            }
        }

        foreach ($groups as &$group) {
            $items = collect($group['items'])
                ->sortBy([['date', 'asc'], ['id', 'asc']])
                ->take(self::LIMIT)
                ->values()
                ->all();
            $group['items'] = array_values($items);
        }
        unset($group);

        return $groups;
    }

    /** @return array{counts: array<string, int>, items: list<array<string, mixed>>} */
    private function quotes(Company $company, CarbonImmutable $localDate): array
    {
        $base = DB::connection(config('database.tenant_connection'))
            ->table('documents')
            ->join('quotes', function ($join): void {
                $join->on('quotes.company_id', '=', 'documents.company_id')
                    ->on('quotes.document_id', '=', 'documents.id');
            })
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->where('quotes.lifecycle', 'SENT')
            ->whereBetween('quotes.valid_until', [
                $localDate->toDateString(),
                $localDate->addDays(14)->toDateString(),
            ])
            ->whereNotNull('documents.currency_code');
        $counts = (clone $base)->groupBy('documents.currency_code')
            ->select('documents.currency_code')
            ->selectRaw('COUNT(*) AS aggregate')
            ->pluck('aggregate', 'currency_code')
            ->mapWithKeys(fn (mixed $count, mixed $currency): array => [(string) $currency => (int) $count])
            ->all();
        $source = (clone $base)
            ->select([
                'documents.id', 'documents.rendered_number', 'documents.currency_code',
                'documents.currency_precision', 'documents.total', 'quotes.valid_until',
            ])
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY documents.currency_code ORDER BY quotes.valid_until, documents.id) AS dashboard_rank');
        $items = array_values($this->ranked($source)->get()->map(function (stdClass $row) use ($company, $localDate): array {
            $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);
            $date = new CarbonImmutable((string) $row->valid_until);

            return [
                'id' => 'quote:'.$row->id,
                'kind' => 'QUOTE',
                'title' => (string) $row->rendered_number,
                'subtitle' => $row->customer_name,
                'amount' => (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
                'currencyCode' => (string) $row->currency_code,
                'date' => $date->toDateString(),
                'daysUntil' => (int) $localDate->diffInDays($date),
                'url' => route('quotes.edit', [$company, $row->id], false),
            ];
        })->values()->all());

        return ['counts' => $counts, 'items' => $items];
    }

    /** @return array{counts: array<string, int>, items: list<array<string, mixed>>} */
    private function recurring(Company $company, CarbonImmutable $localDate): array
    {
        $base = DB::connection(config('database.tenant_connection'))
            ->table('recurring_templates')
            ->join('customers', function ($join): void {
                $join->on('customers.company_id', '=', 'recurring_templates.company_id')
                    ->on('customers.id', '=', 'recurring_templates.customer_id');
            })
            ->leftJoin('recurring_template_customer_values as values', function ($join): void {
                $join->on('values.company_id', '=', 'recurring_templates.company_id')
                    ->on('values.recurring_template_id', '=', 'recurring_templates.id');
            })
            ->leftJoin('company_currencies as customer_currency', function ($join): void {
                $join->on('customer_currency.company_id', '=', 'customers.company_id')
                    ->on('customer_currency.id', '=', 'customers.currency_id')
                    ->where('customer_currency.active', true);
            })
            ->leftJoin('company_currencies as default_currency', function ($join): void {
                $join->on('default_currency.company_id', '=', 'recurring_templates.company_id')
                    ->where('default_currency.active', true)
                    ->where('default_currency.is_default', true);
            })
            ->where('recurring_templates.state', 'ACTIVE')
            ->whereBetween('recurring_templates.next_occurrence_date', [
                $localDate->toDateString(),
                $localDate->addDays(7)->toDateString(),
            ])
            ->whereRaw('COALESCE(values.currency_code, customer_currency.currency_code, default_currency.currency_code) IS NOT NULL');
        $currency = 'COALESCE(values.currency_code, customer_currency.currency_code, default_currency.currency_code)';
        $counts = (clone $base)->groupByRaw($currency)
            ->selectRaw("{$currency} AS currency_code")
            ->selectRaw('COUNT(*) AS aggregate')
            ->pluck('aggregate', 'currency_code')
            ->mapWithKeys(fn (mixed $count, mixed $currency): array => [(string) $currency => (int) $count])
            ->all();
        $source = (clone $base)
            ->select([
                'recurring_templates.id', 'recurring_templates.internal_name',
                'recurring_templates.next_occurrence_date',
            ])
            ->selectRaw("{$currency} AS currency_code")
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY {$currency} ORDER BY recurring_templates.next_occurrence_date, recurring_templates.id) AS dashboard_rank");
        $items = array_values($this->ranked($source)->get()->map(function (stdClass $row) use ($company, $localDate): array {
            $date = new CarbonImmutable((string) $row->next_occurrence_date);

            return [
                'id' => 'recurring:'.$row->id,
                'kind' => 'RECURRING',
                'title' => (string) $row->internal_name,
                'subtitle' => null,
                'amount' => null,
                'currencyCode' => (string) $row->currency_code,
                'date' => $date->toDateString(),
                'daysUntil' => (int) $localDate->diffInDays($date),
                'url' => route('recurring.edit', [$company, $row->id], false),
            ];
        })->values()->all());

        return ['counts' => $counts, 'items' => $items];
    }

    private function ranked(Builder $source): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->query()->fromSub($source, 'dashboard_upcoming')
            ->where('dashboard_rank', '<=', self::LIMIT)
            ->orderBy('currency_code')->orderBy('dashboard_rank');
    }
}
