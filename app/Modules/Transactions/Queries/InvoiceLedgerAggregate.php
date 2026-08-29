<?php

namespace App\Modules\Transactions\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class InvoiceLedgerAggregate
{
    public function query(): Builder
    {
        return DB::connection(config('database.tenant_connection'))
            ->table('invoice_transactions')
            ->select(['company_id', 'invoice_id'])
            ->selectRaw(<<<'SQL'
                SUM(CASE
                    WHEN kind = 'PAYMENT' THEN amount
                    WHEN kind = 'REFUND' THEN -amount
                    WHEN kind = 'ADJUSTMENT' AND adjustment_direction = 'INCREASE_PAID' THEN amount
                    WHEN kind = 'ADJUSTMENT' AND adjustment_direction = 'DECREASE_PAID' THEN -amount
                    ELSE 0 END) AS net_paid
                SQL)
            ->groupBy('company_id', 'invoice_id');
    }
}
