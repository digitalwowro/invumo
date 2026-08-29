<?php

namespace App\Modules\Companies\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CompanyInvitationIdentityLock
{
    public function acquire(string $normalizedEmail): void
    {
        $connection = DB::connection(config('database.tenant_connection'));

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Invitation identity locks require an active transaction.');
        }

        $connection->selectOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$normalizedEmail],
        );
    }
}
