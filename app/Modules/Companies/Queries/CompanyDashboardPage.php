<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Money\DecimalRules;
use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Data\ResolvedInvoiceState;
use App\Modules\Transactions\Queries\InvoiceLedgerAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyDashboardPage
{
    private const RECENT_LIMIT = 5;

    private const CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    public function __construct(
        private CompanyAbilityCheck $abilities,
        private InvoiceLedgerAggregate $ledger,
    ) {}

    /** @return array<string, mixed> */
    public function for(Company $company, User $actor): array
    {
        if (! $this->abilities->allowsAll(
            $actor,
            $company,
            CompanyAbility::ViewInvoices,
            CompanyAbility::ViewTransactions,
        )) {
            throw new AuthorizationException;
        }

        $settings = CompanySetting::query()->firstOrFail();
        $localDate = Date::now($settings->timezone ?? 'UTC')->toImmutable()->startOfDay();

        return [
            'currencyGroups' => $this->currencyGroups($localDate),
            'recentInvoices' => $this->recentInvoices($company, $localDate),
            'invoicesUrl' => route('invoices.index', $company, false),
        ];
    }

    /** @return list<array<string, int|string>> */
    private function currencyGroups(CarbonImmutable $localDate): array
    {
        $groups = [];

        foreach ($this->invoiceMetrics($localDate)->get() as $row) {
            $code = (string) $row->currency_code;
            $groups[$code] = [
                'currencyCode' => $code,
                'precision' => DecimalRules::currencyPrecision((int) $row->currency_precision),
                'unpaidCount' => (int) $row->unpaid_count,
                'overdueCount' => (int) $row->overdue_count,
                'overdueTotal' => (string) $row->overdue_total,
                'paidThisMonth' => '0',
                'outstandingTotal' => (string) $row->outstanding_total,
            ];
        }

        foreach ($this->paymentMetrics($localDate)->get() as $row) {
            $code = (string) $row->currency_code;
            $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);
            $groups[$code] ??= [
                'currencyCode' => $code,
                'precision' => $precision,
                'unpaidCount' => 0,
                'overdueCount' => 0,
                'overdueTotal' => '0',
                'paidThisMonth' => '0',
                'outstandingTotal' => '0',
            ];
            $groups[$code]['precision'] = max($groups[$code]['precision'], $precision);
            $groups[$code]['paidThisMonth'] = (string) $row->paid_this_month;
        }

        ksort($groups);

        return array_values(array_map(function (array $group): array {
            $precision = $group['precision'];

            foreach (['overdueTotal', 'paidThisMonth', 'outstandingTotal'] as $key) {
                $group[$key] = (string) DecimalRules::moneySource($group[$key])->toScale($precision);
            }

            return $group;
        }, $groups));
    }

    private function invoiceMetrics(CarbonImmutable $localDate): Builder
    {
        return $this->invoiceBase()
            ->where('invoices.lifecycle', 'ISSUED')
            ->whereNotNull('documents.currency_code')
            ->whereNotNull('documents.currency_precision')
            ->groupBy('documents.currency_code')
            ->select('documents.currency_code')
            ->selectRaw('MAX(documents.currency_precision) AS currency_precision')
            ->selectRaw('COUNT(*) FILTER (WHERE documents.total > COALESCE(ledger.net_paid, 0)) AS unpaid_count')
            ->selectRaw(
                'COUNT(*) FILTER (WHERE documents.total > COALESCE(ledger.net_paid, 0) AND invoices.due_date < ?) AS overdue_count',
                [$localDate->toDateString()],
            )
            ->selectRaw('COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE documents.total > COALESCE(ledger.net_paid, 0)), 0) AS outstanding_total')
            ->selectRaw(
                'COALESCE(SUM(documents.total - COALESCE(ledger.net_paid, 0)) FILTER (WHERE documents.total > COALESCE(ledger.net_paid, 0) AND invoices.due_date < ?), 0) AS overdue_total',
                [$localDate->toDateString()],
            );
    }

    private function paymentMetrics(CarbonImmutable $localDate): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('invoice_transactions')
            ->where('kind', 'PAYMENT')
            ->whereBetween('transaction_date', [
                $localDate->startOfMonth()->toDateString(),
                $localDate->endOfMonth()->toDateString(),
            ])
            ->groupBy('currency_code')
            ->select('currency_code')
            ->selectRaw('MAX(currency_precision) AS currency_precision')
            ->selectRaw('SUM(amount) AS paid_this_month');
    }

    /** @return list<array<string, mixed>> */
    private function recentInvoices(Company $company, CarbonImmutable $localDate): array
    {
        return array_values($this->invoiceBase()
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->orderByDesc('documents.updated_at')
            ->orderByDesc('documents.id')
            ->limit(self::RECENT_LIMIT)
            ->select([
                'documents.id', 'documents.rendered_number', 'documents.issue_date',
                'documents.currency_code', 'documents.currency_precision', 'documents.total',
                'invoices.lifecycle', 'invoices.due_date',
            ])
            ->selectRaw('COALESCE(ledger.net_paid, 0) AS net_paid')
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name')
            ->get()
            ->map(fn (stdClass $row): array => $this->recentRow($company, $row, $localDate))
            ->all());
    }

    private function invoiceBase(): Builder
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
    private function recentRow(Company $company, stdClass $row, CarbonImmutable $localDate): array
    {
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
            'issueDate' => $row->issue_date,
            'dueDate' => $row->due_date,
            'lifecycle' => $lifecycle->value,
            'paymentState' => $state->paymentState?->value,
            'isOverdue' => $state->isOverdue,
            'total' => $precision === null
                ? null
                : (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
            'currencyCode' => $row->currency_code,
            'viewUrl' => route('invoices.current.show', [$company, $row->id], false),
        ];
    }
}
