<?php

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Jobs\DeleteErasedCompanyFiles;

final readonly class QueueCompanyErasureFileCleanup
{
    public function handle(string $erasureEventId): void
    {
        DeleteErasedCompanyFiles::dispatch($erasureEventId)
            ->onConnection('database')
            ->onQueue('default')
            ->afterCommit();
    }
}
