<?php

namespace App\Modules\Delivery\Console;

use App\Modules\Delivery\Actions\ClaimDueJobDispatches;
use Illuminate\Console\Command;

final class DispatchDueJobs extends Command
{
    protected $signature = 'delivery:dispatch-due';

    protected $description = 'Queue due Invumo scheduling dispatches';

    public function handle(ClaimDueJobDispatches $claim): int
    {
        $claim->handle();

        return self::SUCCESS;
    }
}
