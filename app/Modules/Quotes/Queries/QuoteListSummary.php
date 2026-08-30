<?php

namespace App\Modules\Quotes\Queries;

use App\Foundation\Money\DecimalRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class QuoteListSummary
{
    private const string EXPIRED_SQL = "quotes.valid_until IS NOT NULL AND quotes.valid_until < ?::date AND quotes.lifecycle NOT IN ('ACCEPTED', 'REJECTED')";

    /** @return array<string, array{count: int, amounts: list<array{currencyCode: string, amount: string}>}> */
    public function for(CarbonImmutable $localDate): array
    {
        $counts = $this->counts($localDate);
        $summary = [
            'all' => ['count' => (int) $counts->all_count, 'amounts' => []],
            'sent' => ['count' => (int) $counts->sent_count, 'amounts' => []],
            'accepted' => ['count' => (int) $counts->accepted_count, 'amounts' => []],
            'expired' => ['count' => (int) $counts->expired_count, 'amounts' => []],
        ];

        foreach ($this->amounts($localDate)->get() as $row) {
            $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);

            foreach (['sent', 'accepted', 'expired'] as $key) {
                if ((int) $row->{$key.'_count'} === 0) {
                    continue;
                }

                $summary[$key]['amounts'][] = [
                    'currencyCode' => (string) $row->currency_code,
                    'amount' => (string) DecimalRules::moneySource(
                        (string) $row->{$key.'_total'},
                    )->toScale($precision),
                ];
            }
        }

        return $summary;
    }

    private function counts(CarbonImmutable $localDate): stdClass
    {
        return $this->base()
            ->selectRaw('COUNT(*) AS all_count')
            ->selectRaw("COUNT(*) FILTER (WHERE quotes.lifecycle = 'SENT' AND NOT (".self::EXPIRED_SQL.')) AS sent_count', [$localDate->toDateString()])
            ->selectRaw("COUNT(*) FILTER (WHERE quotes.lifecycle = 'ACCEPTED') AS accepted_count")
            ->selectRaw('COUNT(*) FILTER (WHERE '.self::EXPIRED_SQL.') AS expired_count', [$localDate->toDateString()])
            ->firstOrFail();
    }

    private function amounts(CarbonImmutable $localDate): Builder
    {
        return $this->base()
            ->whereNotNull('documents.currency_code')
            ->whereNotNull('documents.currency_precision')
            ->groupBy('documents.currency_code')
            ->orderBy('documents.currency_code')
            ->select('documents.currency_code')
            ->selectRaw('MAX(documents.currency_precision) AS currency_precision')
            ->selectRaw("COUNT(*) FILTER (WHERE quotes.lifecycle = 'SENT' AND NOT (".self::EXPIRED_SQL.')) AS sent_count', [$localDate->toDateString()])
            ->selectRaw("COALESCE(SUM(documents.total) FILTER (WHERE quotes.lifecycle = 'SENT' AND NOT (".self::EXPIRED_SQL.')), 0) AS sent_total', [$localDate->toDateString()])
            ->selectRaw("COUNT(*) FILTER (WHERE quotes.lifecycle = 'ACCEPTED') AS accepted_count")
            ->selectRaw("COALESCE(SUM(documents.total) FILTER (WHERE quotes.lifecycle = 'ACCEPTED'), 0) AS accepted_total")
            ->selectRaw('COUNT(*) FILTER (WHERE '.self::EXPIRED_SQL.') AS expired_count', [$localDate->toDateString()])
            ->selectRaw('COALESCE(SUM(documents.total) FILTER (WHERE '.self::EXPIRED_SQL.'), 0) AS expired_total', [$localDate->toDateString()]);
    }

    private function base(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('documents')
            ->join('quotes', function ($join): void {
                $join->on('quotes.company_id', '=', 'documents.company_id')
                    ->on('quotes.document_id', '=', 'documents.id');
            })
            ->where('documents.kind', 'QUOTE');
    }
}
