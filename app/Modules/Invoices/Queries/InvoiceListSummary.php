<?php

namespace App\Modules\Invoices\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Transactions\Queries\InvoiceLedgerAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class InvoiceListSummary
{
    public function __construct(private InvoiceLedgerAggregate $ledger) {}

    /** @return array<string, array{count: int, amounts: list<array{currencyCode: string, amount: string}>}> */
    public function for(CarbonImmutable $localDate): array
    {
        $counts = $this->counts($localDate);
        $summary = [
            'all' => ['count' => (int) $counts->all_count, 'amounts' => []],
            'awaiting' => ['count' => (int) $counts->awaiting_count, 'amounts' => []],
            'overdue' => ['count' => (int) $counts->overdue_count, 'amounts' => []],
            'drafts' => ['count' => (int) $counts->draft_count, 'amounts' => []],
        ];

        foreach ($this->amounts($localDate)->get() as $row) {
            $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);

            foreach (['awaiting', 'overdue', 'drafts'] as $key) {
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
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total > COALESCE(ledger.net_paid, 0)) AS awaiting_count")
            ->selectRaw(
                "COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total > COALESCE(ledger.net_paid, 0) AND invoices.due_date < ?) AS overdue_count",
                [$localDate->toDateString()],
            )
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'DRAFT') AS draft_count")
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
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total > COALESCE(ledger.net_paid, 0)) AS awaiting_count")
            ->selectRaw("COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total > COALESCE(ledger.net_paid, 0)), 0) AS awaiting_total")
            ->selectRaw(
                "COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total > COALESCE(ledger.net_paid, 0) AND invoices.due_date < ?) AS overdue_count",
                [$localDate->toDateString()],
            )
            ->selectRaw(
                "COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total > COALESCE(ledger.net_paid, 0) AND invoices.due_date < ?), 0) AS overdue_total",
                [$localDate->toDateString()],
            )
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'DRAFT') AS drafts_count")
            ->selectRaw("COALESCE(SUM(documents.total) FILTER (WHERE invoices.lifecycle = 'DRAFT'), 0) AS drafts_total");
    }

    private function base(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('documents')
            ->join('invoices', function ($join): void {
                $join->on('invoices.company_id', '=', 'documents.company_id')
                    ->on('invoices.document_id', '=', 'documents.id');
            })
            ->leftJoinSub($this->ledger->query(), 'ledger', function ($join): void {
                $join->on('ledger.company_id', '=', 'documents.company_id')
                    ->on('ledger.invoice_id', '=', 'documents.id');
            })
            ->where('documents.kind', 'INVOICE');
    }
}
