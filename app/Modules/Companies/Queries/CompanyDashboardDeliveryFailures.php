<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Money\DecimalRules;
use App\Modules\Companies\Models\Company;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CompanyDashboardDeliveryFailures
{
    private const int LIMIT = 4;

    /** @return array<string, array{count: int, items: list<array<string, mixed>>}> */
    public function for(Company $company): array
    {
        $base = DB::connection(config('database.tenant_connection'))
            ->table('email_deliveries')
            ->join('documents', function ($join): void {
                $join->on('documents.company_id', '=', 'email_deliveries.company_id')
                    ->on('documents.id', '=', 'email_deliveries.document_id');
            })
            ->where('email_deliveries.document_kind', 'INVOICE')
            ->whereNotNull('documents.currency_code');
        $counts = $this->failed(clone $base)
            ->groupBy('documents.currency_code')
            ->select('documents.currency_code')
            ->selectRaw('COUNT(*) AS aggregate')
            ->pluck('aggregate', 'currency_code');
        $recipient = DB::connection(config('database.tenant_connection'))
            ->table('email_delivery_recipients')
            ->select('email')
            ->whereColumn('email_delivery_recipients.company_id', 'email_deliveries.company_id')
            ->whereColumn('email_delivery_recipients.delivery_id', 'email_deliveries.id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->limit(1);
        $source = $this->failed(clone $base)
            ->select([
                'email_deliveries.id', 'email_deliveries.failure_category',
                'email_deliveries.failure_summary', 'email_deliveries.dispatch_state',
                'documents.id as document_id', 'documents.rendered_number',
                'documents.currency_code', 'documents.currency_precision', 'documents.total',
            ])
            ->selectSub($recipient, 'recipient_email')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY documents.currency_code ORDER BY COALESCE(email_deliveries.hard_bounced_at, email_deliveries.soft_bounced_at, email_deliveries.feedback_loop_at, email_deliveries.failed_at, email_deliveries.updated_at) DESC, email_deliveries.id DESC) AS dashboard_rank');
        $rows = DB::connection(config('database.tenant_connection'))
            ->query()->fromSub($source, 'delivery_failures')
            ->where('dashboard_rank', '<=', self::LIMIT)
            ->orderBy('currency_code')->orderBy('dashboard_rank')
            ->get()->groupBy('currency_code');

        return $counts->mapWithKeys(function (mixed $count, string $currency) use ($company, $rows): array {
            $items = array_values($rows->get($currency, collect())->map(
                fn (stdClass $row): array => $this->row($company, $row),
            )->values()->all());

            return [$currency => ['count' => (int) $count, 'items' => $items]];
        })->all();
    }

    private function failed(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('email_deliveries.dispatch_state', ['REJECTED', 'UNKNOWN'])
                ->orWhereNotNull('email_deliveries.soft_bounced_at')
                ->orWhereNotNull('email_deliveries.hard_bounced_at')
                ->orWhereNotNull('email_deliveries.feedback_loop_at');
        });
    }

    /** @return array<string, mixed> */
    private function row(Company $company, stdClass $row): array
    {
        $precision = DecimalRules::currencyPrecision((int) $row->currency_precision);

        return [
            'id' => (string) $row->id,
            'invoiceNumber' => (string) $row->rendered_number,
            'recipientEmail' => $row->recipient_email,
            'failure' => $row->failure_summary ?? $row->failure_category ?? $row->dispatch_state,
            'total' => (string) DecimalRules::moneySource((string) $row->total)->toScale($precision),
            'url' => route('invoices.edit', [$company, $row->document_id], false),
        ];
    }
}
