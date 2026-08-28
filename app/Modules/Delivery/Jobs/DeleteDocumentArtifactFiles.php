<?php

namespace App\Modules\Delivery\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Modules\Delivery\Data\DocumentArtifactFile;
use Illuminate\Filesystem\FilesystemManager;

final class DeleteDocumentArtifactFiles extends TenantJob
{
    /** @param list<DocumentArtifactFile> $files */
    public function __construct(string $companyId, string $documentId, public readonly array $files)
    {
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'document-artifacts-delete:'.$documentId,
            component: 'delivery.artifact_cleanup',
        ));
    }

    public function handle(FilesystemManager $filesystems): void
    {
        foreach ($this->files as $file) {
            $filesystems->disk($file->disk)->delete($file->key);
        }
    }
}
