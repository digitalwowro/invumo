<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Jobs\GenerateRecurringInvoices;
use App\Modules\Delivery\Jobs\SendInvoiceReminder;
use App\Modules\Recurring\Actions\SyncRecurringDispatch;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ClaimDueJobDispatches
{
    public function handle(): int
    {
        $connection = DB::connection(config('database.tenant_connection'));

        return $connection->transaction(fn (): int => $this->claim($connection));
    }

    private function claim(ConnectionInterface $connection): int
    {
        $connection->statement('SET LOCAL ROLE invumo_dispatcher');
        $rows = $connection->select(<<<'SQL'
            SELECT id, company_id, target_id, job_type
            FROM job_dispatches
            WHERE status = 'PENDING' AND due_at <= CURRENT_TIMESTAMP
            ORDER BY due_at, id
            FOR UPDATE SKIP LOCKED
            LIMIT 50
            SQL);
        $claimToken = (string) Str::uuid7();

        if ($rows !== []) {
            $connection->update(<<<'SQL'
                UPDATE job_dispatches
                SET status = 'QUEUED', claim_token = ?, claimed_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ANY (?::uuid[])
                SQL, [$claimToken, '{'.implode(',', array_column($rows, 'id')).'}']);
        }

        $connection->statement('RESET ROLE');

        foreach ($rows as $row) {
            if ($row->job_type === 'INVOICE_REMINDER') {
                SendInvoiceReminder::dispatch($row->company_id, $row->target_id)
                    ->onConnection('database')->onQueue('default');
            }

            if ($row->job_type === SyncRecurringDispatch::JOB_TYPE) {
                GenerateRecurringInvoices::dispatch($row->company_id, $row->id)
                    ->onConnection('database')->onQueue('default');
            }
        }

        return count($rows);
    }
}
