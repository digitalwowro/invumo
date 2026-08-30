<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Transactions\Queries\InvoiceLedgerAggregate;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyDashboardMetrics
{
    private const string OUTSTANDING = 'documents.total > COALESCE(ledger.net_paid, 0)';

    public function __construct(private InvoiceLedgerAggregate $ledger) {}

    /** @return list<array<string, mixed>> */
    public function for(CarbonImmutable $localDate): array
    {
        $payments = $this->payments($localDate)->get()->keyBy('currency_code');

        return array_values($this->invoices($localDate)->get()
            ->map(fn (stdClass $row): array => $this->group(
                $row,
                $payments->get((string) $row->currency_code),
            ))
            ->values()
            ->all());
    }

    private function invoices(CarbonImmutable $localDate): Builder
    {
        $today = $localDate->toDateString();
        $monthStart = $localDate->startOfMonth()->toDateString();
        $monthEnd = $localDate->endOfMonth()->toDateString();
        $inThirtyDays = $localDate->addDays(30)->toDateString();
        $inSevenDays = $localDate->addDays(7)->toDateString();
        $thirtyDaysAgo = $localDate->subDays(30)->toDateString();
        $sixtyDaysAgo = $localDate->subDays(60)->toDateString();

        return $this->base()
            ->whereIn('invoices.lifecycle', ['DRAFT', 'ISSUED'])
            ->whereNotNull('documents.currency_code')
            ->whereNotNull('documents.currency_precision')
            ->groupBy('documents.currency_code')
            ->orderBy('documents.currency_code')
            ->select('documents.currency_code')
            ->selectRaw('MAX(documents.currency_precision) AS currency_precision')
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.') AS unpaid_count')
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date < ?::date) AS overdue_count', [$today])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND ?::date) AS due_soon_count', [$today, $inSevenDays])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND ?::date) AS expected_count', [$today, $inThirtyDays])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'DRAFT') AS draft_count")
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED') AS issued_count")
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.total <= COALESCE(ledger.net_paid, 0)) AS settled_count")
            ->selectRaw('COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = \'ISSUED\' AND '.self::OUTSTANDING.'), 0) AS outstanding_total')
            ->selectRaw('COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = \'ISSUED\' AND '.self::OUTSTANDING.' AND invoices.due_date < ?::date), 0) AS overdue_total', [$today])
            ->selectRaw('COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = \'ISSUED\' AND '.self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND ?::date), 0) AS expected_total', [$today, $inThirtyDays])
            ->selectRaw("COALESCE(SUM(documents.total) FILTER (WHERE invoices.lifecycle = 'DRAFT'), 0) AS draft_total")
            ->selectRaw("COALESCE(SUM(documents.total) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND documents.issue_date BETWEEN ?::date AND ?::date), 0) AS issued_this_month_total", [$monthStart, $monthEnd])
            ->selectRaw('ROUND(COALESCE(AVG(?::date - documents.issue_date) FILTER (WHERE invoices.lifecycle = \'ISSUED\' AND '.self::OUTSTANDING.' AND documents.issue_date IS NOT NULL), 0)) AS average_unpaid_age_days', [$today])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date >= ?::date) AS not_due_count', [$today])
            ->selectRaw("COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date >= ?::date), 0) AS not_due_total', [$today])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND (?::date - 1)) AS days_1_30_count', [$thirtyDaysAgo, $today])
            ->selectRaw("COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND (?::date - 1)), 0) AS days_1_30_total', [$thirtyDaysAgo, $today])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND (?::date - 1)) AS days_31_60_count', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->selectRaw("COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date BETWEEN ?::date AND (?::date - 1)), 0) AS days_31_60_total', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->selectRaw("COUNT(*) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date < ?::date) AS days_60_plus_count', [$sixtyDaysAgo])
            ->selectRaw("COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE invoices.lifecycle = 'ISSUED' AND ".self::OUTSTANDING.' AND invoices.due_date < ?::date), 0) AS days_60_plus_total', [$sixtyDaysAgo]);
    }

    private function payments(CarbonImmutable $localDate): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('invoice_transactions')
            ->join('invoices', function ($join): void {
                $join->on('invoices.company_id', '=', 'invoice_transactions.company_id')
                    ->on('invoices.document_id', '=', 'invoice_transactions.invoice_id');
            })
            ->where('kind', 'PAYMENT')
            ->where('invoices.lifecycle', 'ISSUED')
            ->whereBetween('transaction_date', [
                $localDate->startOfMonth()->toDateString(),
                $localDate->endOfMonth()->toDateString(),
            ])
            ->groupBy('invoice_transactions.currency_code')
            ->select('invoice_transactions.currency_code')
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('SUM(invoice_transactions.amount) AS paid_this_month');
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

    /** @return array<string, mixed> */
    private function group(stdClass $row, ?stdClass $payment): array
    {
        $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);
        $money = fn (mixed $value): string => (string) DecimalRules::moneySource((string) ($value ?? '0'))
            ->toScale($precision);
        $outstanding = DecimalRules::moneySource((string) $row->outstanding_total);
        $overdue = DecimalRules::moneySource((string) $row->overdue_total);
        $overdueShare = $outstanding->isZero() ? 0 : (int) (string) $overdue
            ->dividedBy($outstanding, 4, RoundingMode::HalfUp)
            ->multipliedBy(100)
            ->toScale(0, RoundingMode::HalfUp);
        $issuedCount = (int) $row->issued_count;

        return [
            'currencyCode' => (string) $row->currency_code,
            'precision' => $precision,
            'unpaidCount' => (int) $row->unpaid_count,
            'overdueCount' => (int) $row->overdue_count,
            'dueSoonCount' => (int) $row->due_soon_count,
            'overdueTotal' => $money($row->overdue_total),
            'paidThisMonth' => $money($payment?->paid_this_month),
            'paidThisMonthCount' => $payment === null ? 0 : (int) $payment->payment_count,
            'outstandingTotal' => $money($row->outstanding_total),
            'expectedNext30Total' => $money($row->expected_total),
            'expectedNext30Count' => (int) $row->expected_count,
            'issuedThisMonthTotal' => $money($row->issued_this_month_total),
            'draftCount' => (int) $row->draft_count,
            'draftTotal' => $money($row->draft_total),
            'settledRate' => $issuedCount === 0
                ? 0 : (int) round(((int) $row->settled_count / $issuedCount) * 100),
            'overdueShare' => $overdueShare,
            'averageUnpaidAgeDays' => (int) $row->average_unpaid_age_days,
            'aging' => array_map(fn (string $key): array => [
                'key' => $key,
                'count' => (int) $row->{$key.'_count'},
                'total' => $money($row->{$key.'_total'}),
            ], ['not_due', 'days_1_30', 'days_31_60', 'days_60_plus']),
        ];
    }
}
