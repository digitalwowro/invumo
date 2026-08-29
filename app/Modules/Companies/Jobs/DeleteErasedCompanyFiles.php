<?php

namespace App\Modules\Companies\Jobs;

use App\Modules\Companies\Data\ErasedCompanyFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeleteErasedCompanyFiles implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @param list<ErasedCompanyFile> $files */
    public function __construct(
        public readonly string $companyId,
        public readonly array $files,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function handle(FilesystemManager $filesystems): void
    {
        foreach ($this->files as $file) {
            $filesystems->disk($file->disk)->delete($file->key);
        }
    }
}
