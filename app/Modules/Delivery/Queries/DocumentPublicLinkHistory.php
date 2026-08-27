<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Models\PublicDocumentLink;
use Illuminate\Database\Eloquent\Collection;

final readonly class DocumentPublicLinkHistory
{
    public function exists(string $documentId): bool
    {
        return PublicDocumentLink::query()
            ->where('document_id', $documentId)
            ->exists();
    }

    /** @return Collection<int, PublicDocumentLink> */
    public function lock(string $documentId): Collection
    {
        return PublicDocumentLink::query()
            ->where('document_id', $documentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
