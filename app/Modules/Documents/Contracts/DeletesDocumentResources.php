<?php

namespace App\Modules\Documents\Contracts;

use App\Modules\Documents\Data\LockedDocumentDeletionResources;

interface DeletesDocumentResources
{
    public function lock(string $documentId): LockedDocumentDeletionResources;

    public function delete(string $companyId, string $documentId): void;
}
