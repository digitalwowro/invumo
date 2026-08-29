<?php

namespace App\Modules\Companies\Jobs;

use App\Modules\Companies\Actions\CleanCompanyErasureFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class DeleteErasedCompanyFiles implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 86400;

    public function __construct(public readonly string $erasureEventId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function handle(CleanCompanyErasureFiles $cleanup): void
    {
        $cleanup->handle($this->erasureEventId);
    }

    public function failed(?Throwable $exception): void
    {
        app(CleanCompanyErasureFiles::class)->recordExhaustion($this->erasureEventId);
    }

    public function uniqueId(): string
    {
        return 'company-erasure-files:'.$this->erasureEventId;
    }

    public function uniqueVia(): Repository
    {
        return Cache::store('tenant_jobs');
    }
}
