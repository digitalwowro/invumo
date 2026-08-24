<?php

namespace App\Modules\Platform\Support;

use Illuminate\Support\Facades\DB;

final readonly class PlatformOperatorMutationLock
{
    public function acquire(): void
    {
        DB::connection(config('database.tenant_connection'))
            ->selectOne("SELECT pg_advisory_xact_lock(hashtext('invumo.platform_operators'))");
    }
}
