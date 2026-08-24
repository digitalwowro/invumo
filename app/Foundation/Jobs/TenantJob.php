<?php

namespace App\Foundation\Jobs;

use App\Foundation\Jobs\Middleware\RunTenantJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class TenantJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 604800;

    public function __construct(public readonly JobIdentity $identity) {}

    /** @return list<int> */
    final public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    final public function uniqueId(): string
    {
        return $this->identity->uniqueHash();
    }

    final public function uniqueVia(): Repository
    {
        return Cache::store('tenant_jobs');
    }

    /** @return list<class-string> */
    final public function middleware(): array
    {
        return [RunTenantJob::class];
    }

    final public function correlationId(): string
    {
        $queuedUuid = $this->job?->uuid();

        return is_string($queuedUuid) && Str::isUuid($queuedUuid)
            ? $queuedUuid
            : (string) Str::uuid();
    }
}
