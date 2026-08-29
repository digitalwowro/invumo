<?php

namespace App\Modules\Companies\Console;

use App\Modules\Companies\Actions\ReconcileCompanyErasureFileCleanup;
use Illuminate\Console\Command;

final class ReconcileErasedCompanyFiles extends Command
{
    protected $signature = 'company-erasure:reconcile-files';

    protected $description = 'Queue cleanup for retained files from erased Companies';

    public function handle(ReconcileCompanyErasureFileCleanup $reconcile): int
    {
        $reconcile->handle();

        return self::SUCCESS;
    }
}
