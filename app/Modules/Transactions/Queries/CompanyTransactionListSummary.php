<?php

namespace App\Modules\Transactions\Queries;

use App\Foundation\Money\DecimalRules;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class CompanyTransactionListSummary
{
    /** @return array<string, array{count: int, amounts: list<array{currencyCode: string, amount: string}>}> */
    public function get(): array
    {
        $counts = $this->base()
            ->selectRaw('COUNT(*) AS all_count')
            ->selectRaw("COUNT(*) FILTER (WHERE kind = 'PAYMENT') AS payments_count")
            ->selectRaw("COUNT(*) FILTER (WHERE kind = 'REFUND') AS refunds_count")
            ->selectRaw("COUNT(*) FILTER (WHERE kind = 'ADJUSTMENT') AS adjustments_count")
            ->firstOrFail();
        $summary = [
            'all' => ['count' => (int) $counts->all_count, 'amounts' => []],
            'payments' => ['count' => (int) $counts->payments_count, 'amounts' => []],
            'refunds' => ['count' => (int) $counts->refunds_count, 'amounts' => []],
            'adjustments' => ['count' => (int) $counts->adjustments_count, 'amounts' => []],
        ];

        foreach ($this->amounts()->get() as $row) {
            $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);

            foreach (['payments', 'refunds', 'adjustments'] as $key) {
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

    private function amounts(): Builder
    {
        return $this->base()
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->select('currency_code')
            ->selectRaw('MAX(currency_precision) AS currency_precision')
            ->selectRaw("COUNT(*) FILTER (WHERE kind = 'PAYMENT') AS payments_count")
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE kind = 'PAYMENT'), 0) AS payments_total")
            ->selectRaw("COUNT(*) FILTER (WHERE kind = 'REFUND') AS refunds_count")
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE kind = 'REFUND'), 0) AS refunds_total")
            ->selectRaw("COUNT(*) FILTER (WHERE kind = 'ADJUSTMENT') AS adjustments_count")
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE kind = 'ADJUSTMENT'), 0) AS adjustments_total");
    }

    private function base(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('invoice_transactions');
    }
}
