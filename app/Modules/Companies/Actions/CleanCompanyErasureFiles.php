<?php

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Exceptions\CompanyErasureFileCleanupIncomplete;
use App\Modules\Companies\Exceptions\ErasureStorageConfigurationChanged;
use App\Modules\Companies\Models\CompanyErasureFile;
use App\Modules\Companies\Support\ErasureStorageFingerprint;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class CleanCompanyErasureFiles
{
    public function __construct(
        private FilesystemManager $filesystems,
        private ErasureStorageFingerprint $storageFingerprint,
    ) {}

    public function handle(string $erasureEventId): void
    {
        $ids = CompanyErasureFile::query()
            ->where('data_erasure_event_id', $erasureEventId)
            ->whereNull('completed_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                $this->clean((string) $id);
            } catch (ErasureStorageConfigurationChanged) {
                $this->recordFailure((string) $id, 'STORAGE_CONFIGURATION_CHANGED');
            } catch (Throwable) {
                $this->recordFailure((string) $id, 'STORAGE_UNAVAILABLE');
            }
        }

        if (CompanyErasureFile::query()
            ->where('data_erasure_event_id', $erasureEventId)
            ->whereNull('completed_at')->exists()) {
            throw new CompanyErasureFileCleanupIncomplete;
        }
    }

    public function recordExhaustion(string $erasureEventId): void
    {
        CompanyErasureFile::query()
            ->where('data_erasure_event_id', $erasureEventId)
            ->whereNull('completed_at')
            ->update([
                'last_failure_category' => 'RETRIES_EXHAUSTED',
                'last_failure_summary' => 'Private file cleanup remains pending after bounded retries.',
            ]);
    }

    private function clean(string $id): void
    {
        $file = $this->beginAttempt($id);

        if ($file === null) {
            return;
        }

        if (! hash_equals($file['fingerprint'], $this->storageFingerprint->for($file['disk']))) {
            throw new ErasureStorageConfigurationChanged;
        }

        $disk = $this->filesystems->disk($file['disk']);

        if ($disk->exists($file['key'])) {
            $disk->delete($file['key']);
        }

        if ($disk->exists($file['key'])) {
            throw new RuntimeException('Erased Company file remains present after cleanup.');
        }

        $this->complete($id);
    }

    /** @return array{disk: string, key: string, fingerprint: string}|null */
    private function beginAttempt(string $id): ?array
    {
        return DB::connection(config('database.tenant_connection'))->transaction(
            function () use ($id): ?array {
                $file = CompanyErasureFile::query()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($file->completed_at !== null) {
                    return null;
                }

                $file->update([
                    'attempt_count' => $file->attempt_count + 1,
                    'last_attempted_at' => now(),
                    'last_failure_category' => null,
                    'last_failure_summary' => null,
                ]);

                return [
                    'disk' => (string) $file->storage_disk,
                    'key' => (string) $file->storage_key,
                    'fingerprint' => (string) $file->storage_configuration_fingerprint,
                ];
            },
            3,
        );
    }

    private function complete(string $id): void
    {
        DB::connection(config('database.tenant_connection'))->transaction(function () use ($id): void {
            $file = CompanyErasureFile::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($file->completed_at !== null) {
                return;
            }

            $file->update([
                'storage_disk' => null,
                'storage_key' => null,
                'storage_configuration_fingerprint' => null,
                'last_failure_category' => null,
                'last_failure_summary' => null,
                'completed_at' => now(),
            ]);
        }, 3);
    }

    private function recordFailure(string $id, string $category): void
    {
        DB::connection(config('database.tenant_connection'))->transaction(function () use ($id, $category): void {
            $file = CompanyErasureFile::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($file->completed_at === null) {
                $file->update([
                    'last_failure_category' => $category,
                    'last_failure_summary' => 'Private file cleanup could not be confirmed.',
                ]);
            }
        }, 3);
    }
}
