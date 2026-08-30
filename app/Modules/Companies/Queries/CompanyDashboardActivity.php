<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Companies\Models\Company;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Data\ResolvedInvoiceState;
use App\Modules\Transactions\Queries\InvoiceLedgerAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyDashboardActivity
{
    private const int PANEL_LIMIT = 4;

    private const int RECENT_LIMIT = 5;

    private const string CUSTOMER_NAME = "CASE WHEN customer.type = 'COMPANY' THEN customer.legal_name ELSE concat_ws(' ', customer.first_name, customer.last_name) END";

    public function __construct(
        private InvoiceLedgerAggregate $ledger,
        private CompanyDashboardDeliveryFailures $deliveryFailures,
        private CompanyDashboardUpcoming $upcoming,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function for(
        Company $company,
        CarbonImmutable $localDate,
        bool $includeQuotes,
        bool $includeRecurring,
    ): array {
        $activity = [];

        foreach (['all', 'unpaid', 'drafts'] as $scope) {
            foreach ($this->recentInvoices($company, $localDate, $scope) as $currency => $rows) {
                $activity[$currency]['recentInvoices'][$scope] = $rows;
            }
        }

        foreach ($this->attention($company, $localDate) as $currency => $rows) {
            $activity[$currency]['attention'] = $rows;
        }

        foreach ($this->deliveryFailures->for($company) as $currency => $delivery) {
            $activity[$currency]['deliveryFailures'] = $delivery['items'];
            $activity[$currency]['deliveryFailureCount'] = $delivery['count'];
        }

        foreach ($this->upcoming->for($company, $localDate, $includeQuotes, $includeRecurring) as $currency => $upcoming) {
            $activity[$currency]['upcoming'] = $upcoming['items'];
            $activity[$currency]['upcomingCount'] = $upcoming['count'];
        }

        return $activity;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function recentInvoices(Company $company, CarbonImmutable $localDate, string $scope): array
    {
        $source = $this->invoiceBase()
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->whereNotNull('documents.currency_code')
            ->select([
                'documents.id', 'documents.rendered_number', 'documents.issue_date',
                'documents.currency_code', 'documents.currency_precision', 'documents.total',
                'documents.updated_at', 'invoices.lifecycle', 'invoices.due_date',
            ])
            ->selectRaw('COALESCE(ledger.net_paid, 0) AS net_paid')
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY documents.currency_code ORDER BY documents.updated_at DESC, documents.id DESC) AS dashboard_rank');

        if ($scope === 'unpaid') {
            $source->where('invoices.lifecycle', 'ISSUED')
                ->whereRaw('documents.total > COALESCE(ledger.net_paid, 0)');
        } elseif ($scope === 'drafts') {
            $source->where('invoices.lifecycle', 'DRAFT');
        } else {
            $source->whereIn('invoices.lifecycle', ['DRAFT', 'ISSUED']);
        }

        return $this->ranked($source, self::RECENT_LIMIT)
            ->get()
            ->groupBy('currency_code')
            ->mapWithKeys(fn ($rows, mixed $currency): array => [(string) $currency => array_values($rows
                ->map(fn (stdClass $row): array => $this->invoiceRow($company, $row, $localDate))
                ->values()
                ->all())])
            ->all();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function attention(Company $company, CarbonImmutable $localDate): array
    {
        $source = $this->invoiceBase()
            ->leftJoin('document_customer_snapshots as customer', function ($join): void {
                $join->on('customer.company_id', '=', 'documents.company_id')
                    ->on('customer.document_id', '=', 'documents.id');
            })
            ->where('invoices.lifecycle', 'ISSUED')
            ->whereRaw('documents.total > COALESCE(ledger.net_paid, 0)')
            ->whereBetween('invoices.due_date', [
                '0001-01-01',
                $localDate->addDays(7)->toDateString(),
            ])
            ->whereNotNull('documents.currency_code')
            ->select([
                'documents.id', 'documents.rendered_number', 'documents.currency_code',
                'documents.currency_precision', 'documents.total', 'invoices.due_date',
            ])
            ->selectRaw('COALESCE(ledger.net_paid, 0) AS net_paid')
            ->selectRaw(self::CUSTOMER_NAME.' AS customer_name')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY documents.currency_code ORDER BY invoices.due_date, documents.id) AS dashboard_rank');

        return $this->ranked($source, self::PANEL_LIMIT)
            ->get()
            ->groupBy('currency_code')
            ->mapWithKeys(fn ($rows, mixed $currency): array => [(string) $currency => array_values($rows->map(function (stdClass $row) use ($company, $localDate): array {
                $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);
                $days = $localDate->diffInDays(new CarbonImmutable((string) $row->due_date), false);

                return [
                    'id' => (string) $row->id,
                    'number' => (string) $row->rendered_number,
                    'customerName' => $row->customer_name,
                    'outstanding' => (string) DecimalRules::moneySource((string) $row->total)
                        ->minus(DecimalRules::moneySource((string) $row->net_paid))
                        ->toScale($precision),
                    'dueDate' => (string) $row->due_date,
                    'days' => (int) abs($days),
                    'state' => $days < 0 ? 'OVERDUE' : 'DUE_SOON',
                    'url' => route('invoices.edit', [$company, $row->id], false),
                ];
            })->values()->all())])
            ->all();
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

    private function ranked(Builder $source, int $limit): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->query()
            ->fromSub($source, 'dashboard_rows')
            ->where('dashboard_rank', '<=', $limit)
            ->orderBy('currency_code')
            ->orderBy('dashboard_rank');
    }

    /** @return array<string, mixed> */
    private function invoiceRow(Company $company, stdClass $row, CarbonImmutable $localDate): array
    {
        $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);
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
            'total' => (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
            'outstanding' => (string) DecimalRules::moneySource((string) $row->total)
                ->minus(DecimalRules::moneySource((string) $row->net_paid))
                ->toScale($precision),
            'currencyCode' => (string) $row->currency_code,
            'viewUrl' => route('invoices.edit', [$company, $row->id], false),
        ];
    }
}
