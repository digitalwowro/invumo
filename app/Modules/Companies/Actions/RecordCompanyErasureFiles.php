<?php

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Data\ErasedCompanyFile;
use App\Modules\Companies\Models\CompanyErasureFile;
use App\Modules\Companies\Support\ErasureStorageFingerprint;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RecordCompanyErasureFiles
{
    public function __construct(private ErasureStorageFingerprint $storageFingerprint) {}

    /** @param list<ErasedCompanyFile> $files */
    public function handle(string $erasureEventId, array $files): int
    {
        if (DB::connection(config('database.tenant_connection'))->transactionLevel() === 0) {
            throw new LogicException('Company erasure files must be recorded inside the erasure transaction.');
        }

        $unique = [];

        foreach ($files as $file) {
            $unique[$file->disk."\0".$file->key] = $file;
        }

        foreach ($unique as $file) {
            CompanyErasureFile::query()->create([
                'data_erasure_event_id' => $erasureEventId,
                'storage_disk' => $file->disk,
                'storage_key' => $file->key,
                'storage_configuration_fingerprint' => $this->storageFingerprint->for($file->disk),
                'attempt_count' => 0,
                'created_at' => now(),
            ]);
        }

        return count($unique);
    }
}
